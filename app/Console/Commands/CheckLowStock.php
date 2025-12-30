<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Console\Command;

class CheckLowStock extends Command
{
    protected $signature = 'stock:check-low';
    protected $description = 'Check for low stock products and send notifications';

    public function handle()
    {
        $lowStockProducts = Product::whereColumn('stock', '<=', 'min_stock')
            ->where('stock', '>', 0)
            ->get();

        if ($lowStockProducts->isEmpty()) {
            $this->info('No low stock products found.');
            return;
        }

        $operators = User::whereHas('role', function ($query) {
            $query->where('slug', 'operator');
        })->get();

        foreach ($lowStockProducts as $product) {
            $this->info('Low stock: ' . $product->name . ' (Stock: ' . $product->stock . ', Min: ' . $product->min_stock . ')');

            // Notify operators
            foreach ($operators as $operator) {
                $operator->notify(new LowStockNotification($product));
            }
        }

        $this->info('Low stock check completed. ' . $lowStockProducts->count() . ' products found.');
    }
}
