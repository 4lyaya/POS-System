<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \App\Events\ProductStockUpdated::class => [
            \App\Listeners\LogStockMutation::class,
        ],
        \App\Events\SaleCompleted::class => [
            \App\Listeners\UpdateProductStatistics::class,
        ],
        \App\Events\PurchaseCreated::class => [
            \App\Listeners\SendPurchaseNotification::class,
        ],
        \App\Events\LowStockAlert::class => [
            \App\Listeners\SendLowStockNotification::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
