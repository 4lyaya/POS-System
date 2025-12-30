<?php

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateProductStatistics implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(SaleCompleted $event)
    {
        // Update product statistics
        foreach ($event->sale->items as $item) {
            $product = $item->product;

            // Increment total sold count (you might want to store this in a separate table)
            // For now, we can update a counter cache if added to the model
            if (method_exists($product, 'incrementTotalSold')) {
                $product->incrementTotalSold($item->quantity);
            }

            // Update last sold date
            $product->last_sold_at = now();
            $product->save();
        }

        // Update customer statistics if exists
        if ($event->sale->customer) {
            $customer = $event->sale->customer;
            $customer->total_purchases += $event->sale->grand_total;
            $customer->last_purchase = now();
            $customer->save();
        }
    }
}
