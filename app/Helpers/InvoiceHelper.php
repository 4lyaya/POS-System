<?php

namespace App\Helpers;

use App\Models\Sale;
use App\Models\Purchase;

class InvoiceHelper
{
    public static function generateSaleInvoice()
    {
        $prefix = 'INV';
        return self::generateInvoiceNumber($prefix, Sale::class);
    }

    public static function generatePurchaseInvoice()
    {
        $prefix = 'PUR';
        return self::generateInvoiceNumber($prefix, Purchase::class);
    }

    public static function generateAdjustmentNumber()
    {
        $prefix = 'ADJ';
        $datePart = date('Ymd');

        $lastNumber = \App\Models\Adjustment::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastNumber && strpos($lastNumber->adjustment_number, $prefix . '-' . $datePart) === 0) {
            $number = intval(substr($lastNumber->adjustment_number, -4)) + 1;
        } else {
            $number = 1;
        }

        return $prefix . '-' . $datePart . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    private static function generateInvoiceNumber($prefix, $model)
    {
        $datePart = date('Ymd');

        $lastInvoice = $model::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastInvoice && strpos($lastInvoice->invoice_number, $prefix . '-' . $datePart) === 0) {
            $number = intval(substr($lastInvoice->invoice_number, -4)) + 1;
        } else {
            $number = 1;
        }

        return $prefix . '-' . $datePart . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
