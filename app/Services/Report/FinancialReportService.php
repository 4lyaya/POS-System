<?php

namespace App\Services\Report;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Expense;
use Carbon\Carbon;

class FinancialReportService
{
    public function generate($startDate, $endDate)
    {
        // Sales data
        $sales = Sale::whereBetween('sale_date', [$startDate, $endDate])->get();
        $totalSales = $sales->sum('grand_total');
        $totalProfit = $this->calculateProfit($sales);

        // Purchases data
        $purchases = Purchase::whereBetween('purchase_date', [$startDate, $endDate])->get();
        $totalPurchases = $purchases->sum('grand_total');

        // Expenses data
        $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])->get();
        $totalExpenses = $expenses->sum('amount');

        // Calculate net income
        $netIncome = $totalProfit - $totalExpenses;

        // Daily breakdown
        $dailyBreakdown = $this->getDailyBreakdown($startDate, $endDate);

        // Category breakdown
        $expenseCategories = $expenses->groupBy('category')->map(function ($group) {
            return [
                'total' => $group->sum('amount'),
                'count' => $group->count(),
            ];
        });

        // Payment status
        $purchasePaymentStatus = [
            'paid' => $purchases->where('payment_status', 'paid')->sum('grand_total'),
            'unpaid' => $purchases->where('payment_status', 'unpaid')->sum('grand_total'),
            'partial' => $purchases->where('payment_status', 'partial')->sum('grand_total'),
        ];

        return [
            'summary' => [
                'total_sales' => $totalSales,
                'total_purchases' => $totalPurchases,
                'total_profit' => $totalProfit,
                'total_expenses' => $totalExpenses,
                'net_income' => $netIncome,
                'profit_margin' => $totalSales > 0 ? ($totalProfit / $totalSales) * 100 : 0,
            ],
            'daily_breakdown' => $dailyBreakdown,
            'expense_categories' => $expenseCategories,
            'purchase_payment_status' => $purchasePaymentStatus,
            'sales_count' => $sales->count(),
            'purchases_count' => $purchases->count(),
            'expenses_count' => $expenses->count(),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    private function calculateProfit($sales)
    {
        $profit = 0;

        foreach ($sales as $sale) {
            $sale->load('items.product');
            foreach ($sale->items as $item) {
                if ($item->product) {
                    $profit += ($item->unit_price - $item->product->purchase_price) * $item->quantity;
                }
            }
        }

        return $profit;
    }

    private function getDailyBreakdown($startDate, $endDate)
    {
        $breakdown = [];
        $currentDate = Carbon::parse($startDate);
        $endDateObj = Carbon::parse($endDate);

        while ($currentDate <= $endDateObj) {
            $date = $currentDate->toDateString();

            $dailySales = Sale::whereDate('sale_date', $date)->sum('grand_total');
            $dailyPurchases = Purchase::whereDate('purchase_date', $date)->sum('grand_total');
            $dailyExpenses = Expense::whereDate('expense_date', $date)->sum('amount');

            $dailyProfit = 0;
            $dailySalesData = Sale::whereDate('sale_date', $date)->with('items.product')->get();
            foreach ($dailySalesData as $sale) {
                foreach ($sale->items as $item) {
                    if ($item->product) {
                        $dailyProfit += ($item->unit_price - $item->product->purchase_price) * $item->quantity;
                    }
                }
            }

            $breakdown[] = [
                'date' => $date,
                'label' => $currentDate->format('d/m'),
                'sales' => $dailySales,
                'purchases' => $dailyPurchases,
                'expenses' => $dailyExpenses,
                'profit' => $dailyProfit,
                'net_income' => $dailyProfit - $dailyExpenses,
            ];

            $currentDate->addDay();
        }

        return $breakdown;
    }
}
