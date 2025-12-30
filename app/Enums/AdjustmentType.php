<?php

namespace App\Enums;

enum AdjustmentType: string
{
    case ADDITION = 'addition';
    case SUBTRACTION = 'subtraction';
    case CORRECTION = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::ADDITION => 'Penambahan',
            self::SUBTRACTION => 'Pengurangan',
            self::CORRECTION => 'Koreksi',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ADDITION => 'Menambah jumlah stok',
            self::SUBTRACTION => 'Mengurangi jumlah stok',
            self::CORRECTION => 'Mengoreksi jumlah stok',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ADDITION => 'fa-plus-circle',
            self::SUBTRACTION => 'fa-minus-circle',
            self::CORRECTION => 'fa-edit',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ADDITION => 'success',
            self::SUBTRACTION => 'danger',
            self::CORRECTION => 'warning',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
