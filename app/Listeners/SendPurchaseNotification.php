<?php

namespace App\Listeners;

use App\Events\PurchaseCreated;
use App\Models\User;
use App\Notifications\PurchaseOrderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPurchaseNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PurchaseCreated $event)
    {
        // Get operators and admins
        $users = User::whereHas('role', function ($query) {
            $query->where('slug', 'operator')
                ->orWhere('slug', 'admin');
        })->where('is_active', true)->get();

        foreach ($users as $user) {
            $user->notify(new PurchaseOrderNotification($event->purchase));
        }
    }
}
