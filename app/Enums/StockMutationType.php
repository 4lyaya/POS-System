<?php

namespace App\Enums;

enum StockMutationType: string
{
    case IN = 'in';
    case OUT = 'out';
    case ADJUSTMENT = 'adjustment';
    case RETURN = 'return';
    case DAMAGE = 'damage';
    case CORRECTION = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::IN => 'Stok Masuk',
            self::OUT => 'Stok Keluar',
            self::ADJUSTMENT => 'Penyesuaian',
            self::RETURN => 'Retur',
            self::DAMAGE => 'Rusak',
            self::CORRECTION => 'Koreksi',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::IN => 'fa-arrow-down',
            self::OUT => 'fa-arrow-up',
            self::ADJUSTMENT => 'fa-exchange-alt',
            self::RETURN => 'fa-undo',
            self::DAMAGE => 'fa-times-circle',
            self::CORRECTION => 'fa-wrench',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::IN => 'success',
            self::OUT => 'danger',
            self::ADJUSTMENT => 'warning',
            self::RETURN => 'info',
            self::DAMAGE => 'secondary',
            self::CORRECTION => 'primary',
        };
    }

    public function isIn(): bool
    {
        return $this === self::IN;
    }

    public function isOut(): bool
    {
        return $this === self::OUT;
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
