<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function search(string $query, int $perPage = 15): Paginator;
    public function getByRole(int $roleId): Collection;
    public function getActiveUsers(): Collection;
    public function createWithRole(array $data, int $roleId): User;
    public function updatePassword(User $user, string $password): User;
    public function toggleStatus(User $user): User;
    public function getSalesPerformance(int $userId, string $period = 'month'): array;
    public function getUsersWithStats(string $period = 'month'): Collection;
}
