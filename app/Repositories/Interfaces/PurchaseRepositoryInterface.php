<?php

namespace App\Repositories\Interfaces;

use App\Models\Purchase;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

interface PurchaseRepositoryInterface
{
    public function getPurchasesByDateRange(Carbon $startDate, Carbon $endDate): Collection;
    public function getPurchasesSummaryByDateRange(Carbon $startDate, Carbon $endDate): array;
    public function getUnpaidPurchases(): Collection;
    public function getPurchasesBySupplier(int $supplierId, string $period = 'all'): Collection;
    public function addPayment(Purchase $purchase, float $amount, array $paymentData = []): Purchase;
    public function getRecentPurchases(int $limit = 10): Collection;
    public function getPurchasesWithDueDateApproaching(int $days = 7): Collection;
}
