<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sale;

    public function __construct(Sale $sale)
    {
        $this->sale = $sale;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('sales');
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->sale->id,
            'invoice_number' => $this->sale->invoice_number,
            'amount' => $this->sale->grand_total,
            'cashier' => $this->sale->user->name,
            'time' => $this->sale->created_at->format('H:i:s'),
        ];
    }
}
