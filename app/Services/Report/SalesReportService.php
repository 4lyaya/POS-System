<?php

namespace App\Services\Report;

use Carbon\Carbon;
use App\Models\Sale;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
    public function generate($startDate, $endDate, $filters = [])
    {
        $query = Sale::with(['customer', 'user', 'items.product']);

        // Apply date range
        $query->whereBetween('sale_date', [$startDate, $endDate]);

        // Apply filters
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        $sales = $query->orderBy('sale_date', 'desc')->get();

        // Calculate summary
        $summary = [
            'total_sales' => $sales->sum('grand_total'),
            'total_transactions' => $sales->count(),
            'total_items' => $sales->sum('items_count'),
            'average_transaction' => $sales->avg('grand_total'),
            'total_tax' => $sales->sum('tax'),
            'total_discount' => $sales->sum('discount'),
        ];

        // Payment method breakdown
        $paymentMethods = $sales->groupBy('payment_method')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('grand_total'),
                'percentage' => $group->sum('grand_total') / ($group->sum('grand_total') > 0 ? $group->sum('grand_total') : 1) * 100,
            ];
        });

        // Daily breakdown for chart
        $dailyBreakdown = [];
        $currentDate = Carbon::parse($startDate);
        $endDateObj = Carbon::parse($endDate);

        while ($currentDate <= $endDateObj) {
            $dailySales = $sales->where('sale_date', $currentDate->toDateString());

            $dailyBreakdown[] = [
                'date' => $currentDate->format('Y-m-d'),
                'label' => $currentDate->format('d/m'),
                'transactions' => $dailySales->count(),
                'sales' => $dailySales->sum('grand_total'),
            ];

            $currentDate->addDay();
        }

        // Top selling products
        $topProducts = Product::with('category')
            ->whereHas('saleItems', function ($query) use ($startDate, $endDate) {
                $query->whereHas('sale', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('sale_date', [$startDate, $endDate]);
                });
            })
            ->withCount(['saleItems as total_sold' => function ($query) use ($startDate, $endDate) {
                $query->select(DB::raw('COALESCE(SUM(quantity), 0)'))
                    ->whereHas('sale', function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('sale_date', [$startDate, $endDate]);
                    });
            }])
            ->withSum(['saleItems as total_revenue' => function ($query) use ($startDate, $endDate) {
                $query->whereHas('sale', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('sale_date', [$startDate, $endDate]);
                });
            }], 'total_price')
            ->orderBy('total_sold', 'desc')
            ->limit(10)
            ->get();

        return [
            'sales' => $sales,
            'summary' => $summary,
            'payment_methods' => $paymentMethods,
            'daily_breakdown' => $dailyBreakdown,
            'top_products' => $topProducts,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    public function getDashboardStats($period = 'today')
    {
        $dateRange = $this->getDateRange($period);

        $sales = Sale::whereBetween('sale_date', [$dateRange['start'], $dateRange['end']])->get();

        $stats = [
            'total_sales' => $sales->sum('grand_total'),
            'total_transactions' => $sales->count(),
            'total_items' => $sales->sum('items_count'),
            'average_transaction' => $sales->avg('grand_total'),
        ];

        // Compare with previous period
        $prevDateRange = $this->getPreviousDateRange($period);
        $prevSales = Sale::whereBetween('sale_date', [$prevDateRange['start'], $prevDateRange['end']])->get();

        $prevStats = [
            'total_sales' => $prevSales->sum('grand_total'),
            'total_transactions' => $prevSales->count(),
        ];

        // Calculate growth
        foreach ($stats as $key => $value) {
            if (isset($prevStats[$key]) && $prevStats[$key] > 0) {
                $stats[$key . '_growth'] = (($value - $prevStats[$key]) / $prevStats[$key]) * 100;
            } else {
                $stats[$key . '_growth'] = $value > 0 ? 100 : 0;
            }
        }

        return $stats;
    }

    public function getSalesChartData($period = 'week')
    {
        $dateRange = $this->getDateRange($period);
        $startDate = Carbon::parse($dateRange['start']);
        $endDate = Carbon::parse($dateRange['end']);

        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Penjualan (Rp)',
                    'data' => [],
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                ],
                [
                    'label' => 'Transaksi',
                    'data' => [],
                    'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                    'borderColor' => 'rgb(16, 185, 129)',
                ]
            ]
        ];

        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateLabel = match ($period) {
                'day' => $currentDate->format('H:i'),
                'week', 'month' => $currentDate->format('d/m'),
                'year' => $currentDate->format('M'),
                default => $currentDate->format('d/m'),
            };

            $dailySales = Sale::whereDate('sale_date', $currentDate->toDateString());
            $salesAmount = $dailySales->sum('grand_total');
            $transactionsCount = $dailySales->count();

            $chartData['labels'][] = $dateLabel;
            $chartData['datasets'][0]['data'][] = $salesAmount;
            $chartData['datasets'][1]['data'][] = $transactionsCount;

            $currentDate->addDay();
        }

        return $chartData;
    }

    private function getDateRange($period)
    {
        $today = Carbon::today();

        return match ($period) {
            'today' => [
                'start' => $today,
                'end' => $today,
            ],
            'yesterday' => [
                'start' => $today->copy()->subDay(),
                'end' => $today->copy()->subDay(),
            ],
            'week' => [
                'start' => $today->copy()->startOfWeek(),
                'end' => $today->copy()->endOfWeek(),
            ],
            'month' => [
                'start' => $today->copy()->startOfMonth(),
                'end' => $today->copy()->endOfMonth(),
            ],
            'last_month' => [
                'start' => $today->copy()->subMonth()->startOfMonth(),
                'end' => $today->copy()->subMonth()->endOfMonth(),
            ],
            'year' => [
                'start' => $today->copy()->startOfYear(),
                'end' => $today->copy()->endOfYear(),
            ],
            default => [
                'start' => $today->copy()->startOfMonth(),
                'end' => $today->copy()->endOfMonth(),
            ],
        };
    }

    private function getPreviousDateRange($period)
    {
        $today = Carbon::today();

        return match ($period) {
            'today' => [
                'start' => $today->copy()->subDay(),
                'end' => $today->copy()->subDay(),
            ],
            'yesterday' => [
                'start' => $today->copy()->subDays(2),
                'end' => $today->copy()->subDays(2),
            ],
            'week' => [
                'start' => $today->copy()->subWeek()->startOfWeek(),
                'end' => $today->copy()->subWeek()->endOfWeek(),
            ],
            'month' => [
                'start' => $today->copy()->subMonth()->startOfMonth(),
                'end' => $today->copy()->subMonth()->endOfMonth(),
            ],
            'last_month' => [
                'start' => $today->copy()->subMonths(2)->startOfMonth(),
                'end' => $today->copy()->subMonths(2)->endOfMonth(),
            ],
            'year' => [
                'start' => $today->copy()->subYear()->startOfYear(),
                'end' => $today->copy()->subYear()->endOfYear(),
            ],
            default => [
                'start' => $today->copy()->subMonth()->startOfMonth(),
                'end' => $today->copy()->subMonth()->endOfMonth(),
            ],
        };
    }
}
