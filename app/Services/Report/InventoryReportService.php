<?php

namespace App\Services\Report;

use App\Models\Product;
use App\Models\Category;
use App\Models\StockMutation;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    public function generate($filters = [])
    {
        $query = Product::with(['category', 'unit']);

        // Apply filters
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['stock_status'])) {
            switch ($filters['stock_status']) {
                case 'low':
                    $query->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0);
                    break;
                case 'out':
                    $query->where('stock', '<=', 0);
                    break;
                case 'normal':
                    $query->whereColumn('stock', '>', 'min_stock');
                    break;
            }
        }

        if (!empty($filters['low_stock_only'])) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        $products = $query->orderBy('category_id')
            ->orderBy('name')
            ->get();

        // Calculate summary
        $summary = [
            'total_products' => $products->count(),
            'total_stock' => $products->sum('stock'),
            'stock_value' => $products->sum(function ($product) {
                return $product->stock * $product->purchase_price;
            }),
            'low_stock_count' => $products->where('stock', '<=', DB::raw('min_stock'))->where('stock', '>', 0)->count(),
            'out_of_stock_count' => $products->where('stock', '<=', 0)->count(),
        ];

        // Category breakdown
        $categoryBreakdown = Category::withCount(['products as total_products'])
            ->withSum('products as total_stock', 'stock')
            ->having('total_products', '>', 0)
            ->get()
            ->map(function ($category) {
                $category->stock_value = $category->products->sum(function ($product) {
                    return $product->stock * $product->purchase_price;
                });
                return $category;
            });

        // Stock movement (last 30 days)
        $startDate = now()->subDays(30);
        $stockMovements = StockMutation::selectRaw('
                DATE(created_at) as date,
                SUM(CASE WHEN mutation_type = "in" THEN quantity ELSE 0 END) as stock_in,
                SUM(CASE WHEN mutation_type = "out" THEN quantity ELSE 0 END) as stock_out
            ')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top moving products
        $topMovingProducts = Product::selectRaw('
                products.*,
                COALESCE(SUM(CASE WHEN stock_mutations.mutation_type = "in" THEN stock_mutations.quantity ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN stock_mutations.mutation_type = "out" THEN stock_mutations.quantity ELSE 0 END), 0) as total_out
            ')
            ->leftJoin('stock_mutations', 'products.id', '=', 'stock_mutations.product_id')
            ->where('stock_mutations.created_at', '>=', $startDate)
            ->groupBy('products.id')
            ->orderByRaw('(total_in + total_out) DESC')
            ->limit(10)
            ->get();

        // Expiring products
        $expiringProducts = Product::whereNotNull('expired_date')
            ->where('expired_date', '>', now())
            ->where('expired_date', '<=', now()->addDays(30))
            ->orderBy('expired_date')
            ->get();

        return [
            'products' => $products,
            'summary' => $summary,
            'category_breakdown' => $categoryBreakdown,
            'stock_movements' => $stockMovements,
            'top_moving_products' => $topMovingProducts,
            'expiring_products' => $expiringProducts,
        ];
    }

    public function getStockAlert()
    {
        $lowStockProducts = Product::whereColumn('stock', '<=', 'min_stock')
            ->where('stock', '>', 0)
            ->with(['category', 'unit'])
            ->orderBy('stock', 'asc')
            ->get();

        $outOfStockProducts = Product::where('stock', '<=', 0)
            ->with(['category', 'unit'])
            ->orderBy('name')
            ->get();

        $expiringProducts = Product::whereNotNull('expired_date')
            ->where('expired_date', '>', now())
            ->where('expired_date', '<=', now()->addDays(7))
            ->orderBy('expired_date')
            ->get();

        return [
            'low_stock' => [
                'count' => $lowStockProducts->count(),
                'products' => $lowStockProducts,
            ],
            'out_of_stock' => [
                'count' => $outOfStockProducts->count(),
                'products' => $outOfStockProducts,
            ],
            'expiring' => [
                'count' => $expiringProducts->count(),
                'products' => $expiringProducts,
            ],
        ];
    }

    public function getStockValueAnalysis()
    {
        $analysis = Product::selectRaw('
                categories.name as category_name,
                COUNT(products.id) as product_count,
                SUM(products.stock) as total_stock,
                SUM(products.stock * products.purchase_price) as stock_value,
                AVG(products.stock * products.purchase_price) as avg_value_per_product,
                MIN(products.stock * products.purchase_price) as min_value,
                MAX(products.stock * products.purchase_price) as max_value
            ')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->groupBy('products.category_id', 'categories.name')
            ->orderBy('stock_value', 'desc')
            ->get();

        return $analysis;
    }
}
