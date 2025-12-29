<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\Category;
use App\Models\StockMutation;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class ProductService extends BaseService
{
    public function createProduct(array $data): Product
    {
        return $this->executeInTransaction(function () use ($data) {
            // Generate product code if not provided
            if (empty($data['code'])) {
                $data['code'] = $this->generateProductCode($data['category_id'] ?? null);
            }

            // Generate slug
            $data['slug'] = $this->generateSlug($data['name']);

            // Handle image upload
            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                $data['image'] = $this->uploadImage($data['image'], 'products');
            }

            // Handle multiple images
            if (isset($data['images']) && is_array($data['images'])) {
                $imagePaths = [];
                foreach ($data['images'] as $image) {
                    if ($image instanceof UploadedFile) {
                        $imagePaths[] = $this->uploadImage($image, 'products/gallery');
                    }
                }
                $data['images'] = $imagePaths;
            }

            // Create product
            $product = Product::create($data);

            // Record initial stock if provided
            if (isset($data['initial_stock']) && $data['initial_stock'] > 0) {
                $this->recordInitialStock($product, $data['initial_stock']);
            }

            return $product;
        }, 'Gagal membuat produk');
    }

    public function updateProduct(Product $product, array $data): Product
    {
        return $this->executeInTransaction(function () use ($product, $data) {
            // Handle image upload if new image provided
            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                // Delete old image if exists
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $data['image'] = $this->uploadImage($data['image'], 'products');
            }

            // Handle multiple images
            if (isset($data['images']) && is_array($data['images'])) {
                $imagePaths = $product->images ?? [];
                foreach ($data['images'] as $image) {
                    if ($image instanceof UploadedFile) {
                        $imagePaths[] = $this->uploadImage($image, 'products/gallery');
                    }
                }
                $data['images'] = $imagePaths;
            }

            // Update product
            $product->update($data);

            return $product->fresh();
        }, 'Gagal memperbarui produk');
    }

    private function generateProductCode($categoryId = null): string
    {
        $prefix = 'PRD';
        $categoryPrefix = '';

        if ($categoryId) {
            $category = Category::find($categoryId);
            if ($category) {
                $categoryPrefix = strtoupper(substr($category->name, 0, 3));
            }
        }

        $code = $prefix . ($categoryPrefix ? '-' . $categoryPrefix : '');

        $lastProduct = Product::where('code', 'like', $code . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastProduct) {
            $lastNumber = intval(substr($lastProduct->code, strlen($code) + 1));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $code . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    private function generateSlug($name): string
    {
        $slug = str_slug($name);
        $count = Product::where('slug', 'like', $slug . '%')->count();

        if ($count > 0) {
            return $slug . '-' . ($count + 1);
        }

        return $slug;
    }

    private function uploadImage(UploadedFile $image, $folder = 'products'): string
    {
        $extension = $image->getClientOriginalExtension();
        $filename = uniqid() . '.' . $extension;

        $path = $image->storeAs($folder, $filename, 'public');

        return $path;
    }

    private function recordInitialStock(Product $product, int $quantity): void
    {
        StockMutation::create([
            'product_id' => $product->id,
            'mutation_type' => 'in',
            'quantity' => $quantity,
            'previous_stock' => 0,
            'current_stock' => $quantity,
            'reference_type' => 'initial',
            'reference_id' => null,
            'notes' => 'Stok awal',
            'user_id' => auth()->id() ?? 1,
        ]);
    }

    public function adjustStock(Product $product, array $data): bool
    {
        return $this->executeInTransaction(function () use ($product, $data) {
            $oldStock = $product->stock;
            $newStock = $data['new_stock'] ?? ($oldStock + $data['adjustment']);

            if ($newStock < 0) {
                throw new \Exception('Stok tidak boleh negatif');
            }

            $product->stock = $newStock;
            $product->save();

            // Record stock mutation
            StockMutation::create([
                'product_id' => $product->id,
                'mutation_type' => 'adjustment',
                'quantity' => abs($newStock - $oldStock),
                'previous_stock' => $oldStock,
                'current_stock' => $newStock,
                'reference_type' => 'adjustment',
                'reference_id' => $data['adjustment_id'] ?? null,
                'notes' => $data['notes'] ?? 'Penyesuaian stok',
                'user_id' => auth()->id(),
            ]);

            return true;
        }, 'Gagal menyesuaikan stok');
    }

    public function getLowStockProducts($threshold = null)
    {
        $query = Product::query();

        if ($threshold) {
            $query->where('stock', '<=', $threshold);
        } else {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        return $query->with('category')
            ->orderBy('stock', 'asc')
            ->get();
    }

    public function getBestSellingProducts($limit = 10, $period = 'month')
    {
        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };

        return Product::selectRaw('
                products.*,
                SUM(sale_items.quantity) as total_sold,
                SUM(sale_items.total_price) as total_revenue
            ')
            ->join('sale_items', 'products.id', '=', 'sale_items.product_id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.sale_date', '>=', $startDate)
            ->groupBy('products.id')
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get();
    }
}
