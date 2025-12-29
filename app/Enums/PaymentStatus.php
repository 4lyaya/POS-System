<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PAID = 'paid';
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PAID => 'Lunas',
            self::UNPAID => 'Belum Bayar',
            self::PARTIAL => 'Bayar Sebagian',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PAID => 'success',
            self::UNPAID => 'danger',
            self::PARTIAL => 'warning',
            self::CANCELLED => 'secondary',
        };
    }

    public function badge(): string
    {
        $color = $this->color();
        $label = $this->label();

        return "<span class='badge bg-{$color}'>{$label}</span>";
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
