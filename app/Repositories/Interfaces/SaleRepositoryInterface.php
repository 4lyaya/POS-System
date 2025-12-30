<?php

namespace App\Repositories\Interfaces;

use App\Models\Sale;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;

interface SaleRepositoryInterface
{
    public function all(): Collection;
    public function paginate(int $perPage = 15): Paginator;
    public function find(int $id): ?Sale;
    public function create(array $data): Sale;
    public function cancel(Sale $sale, string $reason): bool;
    public function getTodaySales(): Collection;
    public function getSalesByDateRange(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): Collection;
    public function getSalesSummaryByDateRange(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array;
    public function getSalesByCashier(int $userId, string $period = 'today'): Collection;
    public function getSalesByPaymentMethod(string $method, string $period = 'month'): Collection;
}
