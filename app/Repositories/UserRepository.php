<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $user)
    {
        parent::__construct($user);
        $this->model = $user;
    }

    public function search(string $query, int $perPage = 15): Paginator
    {
        return $this->model
            ->with('role')
            ->where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function getByRole(int $roleId): Collection
    {
        return $this->model
            ->with('role')
            ->where('role_id', $roleId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getActiveUsers(): Collection
    {
        return $this->model
            ->with('role')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function createWithRole(array $data, int $roleId): User
    {
        $data['role_id'] = $roleId;
        $data['password'] = Hash::make($data['password']);

        return $this->model->create($data);
    }

    public function updatePassword(User $user, string $password): User
    {
        $user->password = Hash::make($password);
        $user->save();

        return $user;
    }

    public function toggleStatus(User $user): User
    {
        $user->is_active = !$user->is_active;
        $user->save();

        return $user;
    }

    public function getSalesPerformance(int $userId, string $period = 'month'): array
    {
        $startDate = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $endDate = now();

        $sales = $user->sales()
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->get();

        return [
            'total_sales' => $sales->count(),
            'total_amount' => $sales->sum('grand_total'),
            'average_sale' => $sales->avg('grand_total'),
            'total_items' => $sales->sum('items_count'),
            'period' => $period,
        ];
    }

    public function getUsersWithStats(string $period = 'month'): Collection
    {
        $startDate = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        return $this->model
            ->with('role')
            ->withCount(['sales as total_sales' => function ($query) use ($startDate) {
                $query->where('sale_date', '>=', $startDate);
            }])
            ->withSum(['sales as total_sales_amount' => function ($query) use ($startDate) {
                $query->where('sale_date', '>=', $startDate);
            }], 'grand_total')
            ->orderBy('total_sales_amount', 'desc')
            ->get();
    }
}
