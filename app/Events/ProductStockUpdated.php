<?php

namespace App\Events;

use App\Models\Product;
use App\Models\StockMutation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductStockUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $product;
    public $mutation;
    public $oldStock;
    public $newStock;

    public function __construct(Product $product, StockMutation $mutation, $oldStock, $newStock)
    {
        $this->product = $product;
        $this->mutation = $mutation;
        $this->oldStock = $oldStock;
        $this->newStock = $newStock;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('stock-updates');
    }
}
