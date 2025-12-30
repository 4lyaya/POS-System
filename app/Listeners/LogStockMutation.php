<?php

namespace App\Listeners;

use App\Events\ProductStockUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogStockMutation
{
    public function handle(ProductStockUpdated $event)
    {
        // Log the stock mutation for audit purposes
        Log::channel('stock')->info('Stock updated', [
            'product_id' => $event->product->id,
            'product_name' => $event->product->name,
            'mutation_type' => $event->mutation->mutation_type,
            'quantity' => $event->mutation->quantity,
            'old_stock' => $event->oldStock,
            'new_stock' => $event->newStock,
            'reference_type' => $event->mutation->reference_type,
            'reference_id' => $event->mutation->reference_id,
            'user_id' => $event->mutation->user_id,
            'timestamp' => now()->toDateTimeString(),
        ]);

        // Check if stock is now low and trigger alert
        if ($event->newStock <= $event->product->min_stock && $event->newStock > 0) {
            event(new \App\Events\LowStockAlert($event->product));
        }
    }
}
