<?php

namespace App\Repositories;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMutation;
use App\Repositories\Interfaces\PurchaseRepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseRepository extends BaseRepository implements PurchaseRepositoryInterface
{
    public function __construct(Purchase $purchase)
    {
        parent::__construct($purchase);
        $this->model = $purchase;
    }

    public function getPurchasesByDateRange(Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->model
            ->with(['supplier', 'user', 'items.product'])
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->orderBy('purchase_date', 'desc')
            ->get();
    }

    public function getPurchasesSummaryByDateRange(Carbon $startDate, Carbon $endDate): array
    {
        $summary = $this->model
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_purchases,
                SUM(grand_total) as total_amount,
                SUM(paid_amount) as total_paid,
                SUM(due_amount) as total_due,
                AVG(grand_total) as average_purchase
            ')
            ->first();

        // Supplier breakdown
        $supplierBreakdown = $this->model
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->selectRaw('supplier_id, COUNT(*) as count, SUM(grand_total) as total')
            ->groupBy('supplier_id')
            ->with('supplier')
            ->get();

        // Payment status breakdown
        $paymentStatusBreakdown = $this->model
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->selectRaw('payment_status, COUNT(*) as count, SUM(grand_total) as total')
            ->groupBy('payment_status')
            ->get();

        // Daily breakdown
        $dailyBreakdown = $this->model
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->selectRaw('DATE(purchase_date) as date, COUNT(*) as purchases, SUM(grand_total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'summary' => $summary,
            'supplier_breakdown' => $supplierBreakdown,
            'payment_status_breakdown' => $paymentStatusBreakdown,
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    public function getUnpaidPurchases(): Collection
    {
        return $this->model
            ->with(['supplier', 'user'])
            ->where('payment_status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->get();
    }

    public function getPurchasesBySupplier(int $supplierId, string $period = 'all'): Collection
    {
        $query = $this->model->where('supplier_id', $supplierId);

        if ($period === 'month') {
            $query->whereMonth('purchase_date', now()->month)
                ->whereYear('purchase_date', now()->year);
        } elseif ($period === 'year') {
            $query->whereYear('purchase_date', now()->year);
        }

        return $query->with(['user', 'items.product'])
            ->orderBy('purchase_date', 'desc')
            ->get();
    }

    public function addPayment(Purchase $purchase, float $amount, array $paymentData = []): Purchase
    {
        return DB::transaction(function () use ($purchase, $amount, $paymentData) {
            if ($amount > $purchase->due_amount) {
                throw new \Exception('Jumlah pembayaran melebihi hutang');
            }

            $purchase->paid_amount += $amount;
            $purchase->due_amount -= $amount;

            if ($purchase->due_amount <= 0) {
                $purchase->payment_status = 'paid';
            } else {
                $purchase->payment_status = 'partial';
            }

            // Update metadata with payment details
            $metadata = $purchase->metadata ?? [];
            $payments = $metadata['payments'] ?? [];

            $payments[] = [
                'amount' => $amount,
                'date' => $paymentData['date'] ?? now()->toDateString(),
                'method' => $paymentData['method'] ?? 'cash',
                'notes' => $paymentData['notes'] ?? null,
                'recorded_by' => auth()->id(),
                'recorded_at' => now(),
            ];

            $metadata['payments'] = $payments;
            $purchase->metadata = $metadata;

            $purchase->save();

            // Update supplier balance
            if ($purchase->supplier) {
                $purchase->supplier->balance -= $amount;
                $purchase->supplier->save();
            }

            return $purchase->fresh();
        });
    }

    public function getRecentPurchases(int $limit = 10): Collection
    {
        return $this->model
            ->with(['supplier', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getPurchasesWithDueDateApproaching(int $days = 7): Collection
    {
        return $this->model
            ->with(['supplier', 'user'])
            ->where('payment_status', '!=', 'paid')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays($days))
            ->where('due_date', '>=', now())
            ->orderBy('due_date', 'asc')
            ->get();
    }
}
