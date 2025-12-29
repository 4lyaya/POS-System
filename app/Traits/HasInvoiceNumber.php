<?php

namespace App\Traits;

trait HasInvoiceNumber
{
    protected static function bootHasInvoiceNumber()
    {
        static::creating(function ($model) {
            if (empty($model->invoice_number)) {
                $model->invoice_number = $model->generateInvoiceNumber();
            }
        });
    }

    protected function generateInvoiceNumber()
    {
        $prefix = strtoupper(substr(class_basename($this), 0, 3));
        $datePart = now()->format('Ymd');

        $lastRecord = self::whereDate('created_at', today())
            ->where('invoice_number', 'like', $prefix . '-' . $datePart . '-%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastRecord) {
            $lastNumber = intval(substr($lastRecord->invoice_number, -4));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . '-' . $datePart . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
