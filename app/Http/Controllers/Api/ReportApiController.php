<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Report\SalesReportService;
use App\Services\Report\InventoryReportService;
use App\Services\Report\FinancialReportService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportApiController extends Controller
{
    protected $salesReportService;
    protected $inventoryReportService;
    protected $financialReportService;

    public function __construct(
        SalesReportService $salesReportService,
        InventoryReportService $inventoryReportService,
        FinancialReportService $financialReportService
    ) {
        $this->salesReportService = $salesReportService;
        $this->inventoryReportService = $inventoryReportService;
        $this->financialReportService = $financialReportService;
    }

    public function stats(Request $request)
    {
        $period = $request->input('period', 'today');

        $stats = $this->salesReportService->getDashboardStats($period);

        // Add inventory stats
        $inventoryAlerts = $this->inventoryReportService->getStockAlert();
        $stats['inventory_alerts'] = [
            'low_stock_count' => $inventoryAlerts['low_stock']['count'],
            'out_of_stock_count' => $inventoryAlerts['out_of_stock']['count'],
            'expiring_count' => $inventoryAlerts['expiring']['count'],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function chart(Request $request)
    {
        $period = $request->input('period', 'week');

        $chartData = $this->salesReportService->getSalesChartData($period);

        return response()->json([
            'success' => true,
            'data' => $chartData,
        ]);
    }

    public function salesReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $filters = $request->only(['user_id', 'payment_method', 'customer_id']);

        $report = $this->salesReportService->generate(
            $request->start_date,
            $request->end_date,
            $filters
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function inventoryReport(Request $request)
    {
        $filters = $request->only(['category_id', 'stock_status', 'low_stock_only']);

        $report = $this->inventoryReportService->generate($filters);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function financialReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $report = $this->financialReportService->generate(
            $request->start_date,
            $request->end_date
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function dailySales(Request $request)
    {
        $date = $request->input('date', today()->toDateString());

        $report = $this->salesReportService->generate($date, $date);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function monthlySales(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));

        $startDate = date("{$year}-{$month}-01");
        $endDate = date("{$year}-{$month}-t", strtotime($startDate));

        $report = $this->salesReportService->generate($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function topProducts(Request $request)
    {
        $period = $request->input('period', 'month');
        $limit = $request->input('limit', 10);

        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };

        $topProducts = \App\Models\Product::selectRaw('
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

        return response()->json([
            'success' => true,
            'data' => $topProducts,
        ]);
    }

    public function stockAlerts()
    {
        $alerts = $this->inventoryReportService->getStockAlert();

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }
}
