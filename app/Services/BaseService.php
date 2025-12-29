<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseService
{
    protected function executeInTransaction(callable $callback, $errorMessage = 'Terjadi kesalahan')
    {
        DB::beginTransaction();

        try {
            $result = $callback();
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($errorMessage . ': ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception($errorMessage, 0, $e);
        }
    }

    protected function generateCode($prefix, $model, $date = null)
    {
        $date = $date ?? now();
        $datePart = $date->format('Ymd');

        $lastRecord = $model::where('code', 'like', $prefix . '-' . $datePart . '-%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastRecord) {
            $lastNumber = intval(substr($lastRecord->code, -4));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . '-' . $datePart . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected function formatCurrency($amount)
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
