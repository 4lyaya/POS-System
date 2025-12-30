<?php

namespace App\Repositories;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMutation;
use App\Repositories\Interfaces\SaleRepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SaleRepository extends BaseRepository implements SaleRepositoryInterface
{
    public function __construct(Sale $sale)
    {
        parent::__construct($sale);
        $this->model = $sale;
    }

    public function cancel(Sale $sale, string $reason): bool
    {
        return DB::transaction(function () use ($sale, $reason) {
            // Return stock for each item
            foreach ($sale->items as $item) {
                $product = $item->product;
                $oldStock = $product->stock;
                $product->increment('stock', $item->quantity);

                // Record stock mutation
                StockMutation::create([
                    'product_id' => $product->id,
                    'mutation_type' => 'in',
                    'quantity' => $item->quantity,
                    'previous_stock' => $oldStock,
                    'current_stock' => $product->stock,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'notes' => 'Pembatalan: ' . $reason,
                    'user_id' => auth()->id(),
                ]);
            }

            // Update sale status
            $sale->update([
                'payment_status' => 'cancelled',
                'metadata' => array_merge($sale->metadata ?? [], [
                    'cancelled_at' => now(),
                    'cancelled_by' => auth()->id(),
                    'cancellation_reason' => $reason
                ])
            ]);

            return true;
        });
    }

    public function getTodaySales(): Collection
    {
        return $this->model
            ->with(['customer', 'user', 'items.product'])
            ->whereDate('sale_date', today())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getSalesByDateRange(Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->model
            ->with(['customer', 'user', 'items.product'])
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->orderBy('sale_date', 'desc')
            ->get();
    }

    public function getSalesSummaryByDateRange(Carbon $startDate, Carbon $endDate): array
    {
        $summary = $this->model
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(grand_total) as total_sales,
                SUM(paid_amount) as total_paid,
                AVG(grand_total) as average_transaction,
                SUM(items_count) as total_items_sold
            ')
            ->first();

        // Get payment method breakdown
        $paymentMethods = $this->model
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->selectRaw('payment_method, COUNT(*) as count, SUM(grand_total) as total')
            ->groupBy('payment_method')
            ->get();

        // Get daily breakdown for chart
        $dailyBreakdown = $this->model
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->selectRaw('DATE(sale_date) as date, COUNT(*) as transactions, SUM(grand_total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'summary' => $summary,
            'payment_methods' => $paymentMethods,
            'daily_breakdown' => $dailyBreakdown
        ];
    }

    public function getSalesByCashier(int $userId, string $period = 'today'): Collection
    {
        $query = $this->model->where('user_id', $userId);

        if ($period === 'today') {
            $query->whereDate('sale_date', today());
        } elseif ($period === 'week') {
            $query->where('sale_date', '>=', now()->subWeek());
        } elseif ($period === 'month') {
            $query->where('sale_date', '>=', now()->subMonth());
        }

        return $query->with(['customer', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getSalesByPaymentMethod(string $method, string $period = 'month'): Collection
    {
        $query = $this->model->where('payment_method', $method);

        if ($period === 'today') {
            $query->whereDate('sale_date', today());
        } elseif ($period === 'week') {
            $query->where('sale_date', '>=', now()->subWeek());
        } elseif ($period === 'month') {
            $query->where('sale_date', '>=', now()->subMonth());
        } elseif ($period === 'year') {
            $query->where('sale_date', '>=', now()->subYear());
        }

        return $query->with(['customer', 'user'])
            ->orderBy('sale_date', 'desc')
            ->get();
    }
}
