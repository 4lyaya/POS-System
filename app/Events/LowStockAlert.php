<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockAlert implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $product;
    public $currentStock;
    public $minStock;

    public function __construct(Product $product)
    {
        $this->product = $product;
        $this->currentStock = $product->stock;
        $this->minStock = $product->min_stock;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('alerts');
    }

    public function broadcastWith()
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_code' => $this->product->code,
            'current_stock' => $this->currentStock,
            'min_stock' => $this->minStock,
            'alert_time' => now()->format('H:i:s'),
        ];
    }
}
