<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';
    case QRIS = 'qris';
    case DEBIT = 'debit';
    case CREDIT = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Tunai',
            self::TRANSFER => 'Transfer Bank',
            self::QRIS => 'QRIS',
            self::DEBIT => 'Kartu Debit',
            self::CREDIT => 'Kartu Kredit',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CASH => 'fa-money-bill',
            self::TRANSFER => 'fa-university',
            self::QRIS => 'fa-qrcode',
            self::DEBIT => 'fa-credit-card',
            self::CREDIT => 'fa-credit-card',
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
