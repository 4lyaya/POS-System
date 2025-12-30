<?php

namespace App\Helpers;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class StockHelper
{
    public static function checkLowStock($threshold = null)
    {
        $query = Product::query();

        if ($threshold) {
            $query->where('stock', '<=', $threshold);
        } else {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        return $query->where('stock', '>', 0)->count();
    }

    public static function getStockValue()
    {
        return Product::sum(DB::raw('stock * purchase_price'));
    }

    public static function getOutOfStockCount()
    {
        return Product::where('stock', '<=', 0)->count();
    }

    public static function getStockStatusColor($stock, $minStock)
    {
        if ($stock <= 0) {
            return 'danger';
        } elseif ($stock <= $minStock) {
            return 'warning';
        } else {
            return 'success';
        }
    }

    public static function getStockStatusText($stock, $minStock)
    {
        if ($stock <= 0) {
            return 'Habis';
        } elseif ($stock <= $minStock) {
            return 'Menipis';
        } else {
            return 'Tersedia';
        }
    }
}
