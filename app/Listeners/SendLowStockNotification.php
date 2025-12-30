<?php

namespace App\Listeners;

use App\Events\LowStockAlert;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendLowStockNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(LowStockAlert $event)
    {
        // Get operators and admins
        $users = User::whereHas('role', function ($query) {
            $query->where('slug', 'operator')
                ->orWhere('slug', 'admin');
        })->where('is_active', true)->get();

        foreach ($users as $user) {
            $user->notify(new LowStockNotification($event->product));
        }
    }
}
