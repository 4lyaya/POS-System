<?php

namespace App\Traits;

use App\Models\StockMutation;

trait HasStockMutation
{
    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'reference');
    }

    public function recordStockIn($productId, $quantity, $notes = null)
    {
        return StockMutation::create([
            'product_id' => $productId,
            'mutation_type' => 'in',
            'quantity' => $quantity,
            'previous_stock' => $this->product->stock,
            'current_stock' => $this->product->stock + $quantity,
            'reference_type' => get_class($this),
            'reference_id' => $this->id,
            'notes' => $notes,
            'user_id' => auth()->id(),
        ]);
    }

    public function recordStockOut($productId, $quantity, $notes = null)
    {
        return StockMutation::create([
            'product_id' => $productId,
            'mutation_type' => 'out',
            'quantity' => $quantity,
            'previous_stock' => $this->product->stock,
            'current_stock' => $this->product->stock - $quantity,
            'reference_type' => get_class($this),
            'reference_id' => $this->id,
            'notes' => $notes,
            'user_id' => auth()->id(),
        ]);
    }
}
