<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $product)
    {
        parent::__construct($product);
        $this->model = $product;
    }

    public function search(string $query, int $perPage = 15): Paginator
    {
        return $this->model
            ->where('name', 'like', "%{$query}%")
            ->orWhere('code', 'like', "%{$query}%")
            ->orWhere('barcode', 'like', "%{$query}%")
            ->with(['category', 'unit'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function getLowStock(int $threshold = null): Collection
    {
        $query = $this->model->with(['category', 'unit']);

        if ($threshold) {
            $query->where('stock', '<=', $threshold);
        } else {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        return $query->where('stock', '>', 0)
            ->orderBy('stock', 'asc')
            ->get();
    }

    public function getBestSelling(int $limit = 10, string $period = 'month'): Collection
    {
        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };

        return $this->model
            ->selectRaw('
                products.*,
                COALESCE(SUM(sale_items.quantity), 0) as total_sold,
                COALESCE(SUM(sale_items.total_price), 0) as total_revenue
            ')
            ->leftJoin('sale_items', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('sales', function ($join) use ($startDate) {
                $join->on('sale_items.sale_id', '=', 'sales.id')
                    ->where('sales.sale_date', '>=', $startDate);
            })
            ->groupBy('products.id')
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getByCategory(int $categoryId): Collection
    {
        return $this->model
            ->where('category_id', $categoryId)
            ->with(['category', 'unit'])
            ->orderBy('name')
            ->get();
    }

    public function updateStock(Product $product, int $quantity, string $type = 'in'): bool
    {
        return DB::transaction(function () use ($product, $quantity, $type) {
            $oldStock = $product->stock;

            if ($type === 'in') {
                $product->increment('stock', $quantity);
            } else {
                if ($product->stock < $quantity) {
                    throw new \Exception("Stok tidak mencukupi. Stok tersedia: {$product->stock}");
                }
                $product->decrement('stock', $quantity);
            }

            // You might want to create stock mutation record here
            // StockMutation::create([...]);

            return true;
        });
    }
}
