<?php

namespace App\Http\Controllers;

use App\Services\Report\SalesReportService;
use App\Services\Inventory\StockService;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->isCashier()) {
            return $this->cashierDashboard($user);
        }

        return $this->adminDashboard($user);
    }

    private function adminDashboard($user)
    {
        // Today's stats
        $todaySales = Sale::whereDate('sale_date', today())->sum('grand_total');
        $todayPurchases = Purchase::whereDate('purchase_date', today())->sum('grand_total');
        $todayTransactions = Sale::whereDate('sale_date', today())->count();
        $todayProfit = Sale::whereDate('sale_date', today())
            ->with('items.product')
            ->get()
            ->sum(function ($sale) {
                return $sale->profit;
            });

        // Monthly stats
        $monthlySales = Sale::whereMonth('sale_date', now()->month)
            ->whereYear('sale_date', now()->year)
            ->sum('grand_total');

        $monthlyPurchases = Purchase::whereMonth('purchase_date', now()->month)
            ->whereYear('purchase_date', now()->year)
            ->sum('grand_total');

        // Low stock products
        $lowStockProducts = Product::whereColumn('stock', '<=', 'min_stock')
            ->where('stock', '>', 0)
            ->limit(10)
            ->get();

        // Out of stock products
        $outOfStockProducts = Product::where('stock', '<=', 0)
            ->limit(10)
            ->get();

        // Recent sales
        $recentSales = Sale::with(['customer', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Recent purchases
        $recentPurchases = Purchase::with(['supplier', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard/Admin', [
            'stats' => [
                'today_sales' => $todaySales,
                'today_purchases' => $todayPurchases,
                'today_transactions' => $todayTransactions,
                'today_profit' => $todayProfit,
                'monthly_sales' => $monthlySales,
                'monthly_purchases' => $monthlyPurchases,
            ],
            'low_stock_products' => $lowStockProducts,
            'out_of_stock_products' => $outOfStockProducts,
            'recent_sales' => $recentSales,
            'recent_purchases' => $recentPurchases,
        ]);
    }

    private function cashierDashboard($user)
    {
        // Today's sales by this cashier
        $todaySales = Sale::whereDate('sale_date', today())
            ->where('user_id', $user->id)
            ->sum('grand_total');

        $todayTransactions = Sale::whereDate('sale_date', today())
            ->where('user_id', $user->id)
            ->count();

        $todayItems = Sale::whereDate('sale_date', today())
            ->where('user_id', $user->id)
            ->sum('items_count');

        // Recent sales by this cashier
        $recentSales = Sale::with('customer')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard/Cashier', [
            'stats' => [
                'today_sales' => $todaySales,
                'today_transactions' => $todayTransactions,
                'today_items' => $todayItems,
            ],
            'recent_sales' => $recentSales,
        ]);
    }
}
