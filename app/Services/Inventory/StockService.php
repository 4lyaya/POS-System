<?php

namespace App\Services\Inventory;

use App\Models\StockMutation;
use App\Models\Product;
use App\Models\Adjustment;
use App\Models\AdjustmentItem;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockService extends BaseService
{
    public function recordStockIn(Product $product, array $data): StockMutation
    {
        return $this->executeInTransaction(function () use ($product, $data) {
            $oldStock = $product->stock;
            $quantity = $data['quantity'];

            $product->increment('stock', $quantity);

            return StockMutation::create([
                'product_id' => $product->id,
                'mutation_type' => 'in',
                'quantity' => $quantity,
                'previous_stock' => $oldStock,
                'current_stock' => $product->stock,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? 'Stok masuk',
                'user_id' => Auth::id(),
            ]);
        }, 'Gagal mencatat stok masuk');
    }

    public function recordStockOut(Product $product, array $data): StockMutation
    {
        return $this->executeInTransaction(function () use ($product, $data) {
            $oldStock = $product->stock;
            $quantity = $data['quantity'];

            // Validate stock
            if ($product->stock < $quantity) {
                throw new \Exception("Stok tidak mencukupi. Stok tersedia: {$product->stock}");
            }

            $product->decrement('stock', $quantity);

            return StockMutation::create([
                'product_id' => $product->id,
                'mutation_type' => 'out',
                'quantity' => $quantity,
                'previous_stock' => $oldStock,
                'current_stock' => $product->stock,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? 'Stok keluar',
                'user_id' => Auth::id(),
            ]);
        }, 'Gagal mencatat stok keluar');
    }

    public function createAdjustment(array $data): Adjustment
    {
        return $this->executeInTransaction(function () use ($data) {
            // Generate adjustment number
            $adjustmentNumber = $this->generateAdjustmentNumber();

            // Create adjustment
            $adjustment = Adjustment::create([
                'adjustment_number' => $adjustmentNumber,
                'adjustment_date' => $data['adjustment_date'] ?? now(),
                'adjustment_type' => $data['adjustment_type'],
                'reason' => $data['reason'],
                'user_id' => Auth::id(),
            ]);

            // Process adjustment items
            $this->processAdjustmentItems($adjustment, $data['items']);

            return $adjustment;
        }, 'Gagal membuat penyesuaian stok');
    }

    private function generateAdjustmentNumber(): string
    {
        $prefix = 'ADJ';
        $datePart = now()->format('Ymd');

        $lastAdjustment = Adjustment::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastAdjustment && strpos($lastAdjustment->adjustment_number, $prefix . '-' . $datePart) === 0) {
            $lastNumber = intval(substr($lastAdjustment->adjustment_number, -4));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . '-' . $datePart . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function processAdjustmentItems(Adjustment $adjustment, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $quantity = $item['quantity'];

            // Create adjustment item
            AdjustmentItem::create([
                'adjustment_id' => $adjustment->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'notes' => $item['notes'] ?? null,
            ]);

            // Update stock based on adjustment type
            $oldStock = $product->stock;

            if ($adjustment->adjustment_type === 'addition') {
                $product->increment('stock', $quantity);
                $mutationType = 'in';
            } elseif ($adjustment->adjustment_type === 'subtraction') {
                if ($product->stock < $quantity) {
                    throw new \Exception("Stok {$product->name} tidak mencukupi untuk pengurangan");
                }
                $product->decrement('stock', $quantity);
                $mutationType = 'out';
            } else {
                // Correction - set stock to specific value
                $product->stock = $quantity;
                $product->save();
                $mutationType = 'adjustment';
                $quantity = abs($quantity - $oldStock);
            }

            // Record stock mutation
            StockMutation::create([
                'product_id' => $product->id,
                'mutation_type' => $mutationType,
                'quantity' => $quantity,
                'previous_stock' => $oldStock,
                'current_stock' => $product->stock,
                'reference_type' => Adjustment::class,
                'reference_id' => $adjustment->id,
                'notes' => 'Penyesuaian: ' . $adjustment->reason,
                'user_id' => Auth::id(),
            ]);
        }
    }

    public function getStockHistory(Product $product, $period = 'month')
    {
        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };

        return StockMutation::with(['reference', 'user'])
            ->where('product_id', $product->id)
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getStockSummary()
    {
        return Product::selectRaw('
                categories.name as category_name,
                COUNT(products.id) as total_products,
                SUM(products.stock) as total_stock,
                SUM(products.stock * products.purchase_price) as stock_value,
                SUM(CASE WHEN products.stock <= products.min_stock THEN 1 ELSE 0 END) as low_stock_count
            ')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->groupBy('products.category_id', 'categories.name')
            ->get();
    }

    public function checkExpiringProducts($days = 30)
    {
        return Product::whereNotNull('expired_date')
            ->where('expired_date', '<=', now()->addDays($days))
            ->where('expired_date', '>', now())
            ->orderBy('expired_date', 'asc')
            ->get();
    }
}
