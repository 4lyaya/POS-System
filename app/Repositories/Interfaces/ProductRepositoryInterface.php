<?php

namespace App\Repositories\Interfaces;

use App\Models\Product;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface
{
    public function all(): Collection;
    public function paginate(int $perPage = 15): Paginator;
    public function find(int $id): ?Product;
    public function create(array $data): Product;
    public function update(Product $product, array $data): Product;
    public function delete(Product $product): bool;
    public function search(string $query, int $perPage = 15): Paginator;
    public function getLowStock(int $threshold = null): Collection;
    public function getBestSelling(int $limit = 10, string $period = 'month'): Collection;
    public function getByCategory(int $categoryId): Collection;
    public function updateStock(Product $product, int $quantity, string $type = 'in'): bool;
}
