<?php

namespace App\Helpers;

class CurrencyHelper
{
    public static function format($amount, $currency = 'IDR')
    {
        if ($currency === 'IDR') {
            return 'Rp ' . number_format($amount, 0, ',', '.');
        }

        return number_format($amount, 2);
    }

    public static function toWords($amount)
    {
        $units = ['', 'ribu', 'juta', 'miliar', 'triliun'];
        $words = [];

        $amount = floor($amount);

        if ($amount == 0) {
            return 'nol rupiah';
        }

        // Handle thousands separator
        $amountStr = strrev(strval($amount));
        $chunks = str_split($amountStr, 3);

        foreach ($chunks as $i => $chunk) {
            $chunk = strrev($chunk);
            if ($chunk != '000') {
                $words[] = self::convertThreeDigit($chunk) . ' ' . $units[$i];
            }
        }

        return implode(' ', array_reverse($words)) . ' rupiah';
    }

    private static function convertThreeDigit($number)
    {
        $ones = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan'];
        $tens = ['', 'sepuluh', 'dua puluh', 'tiga puluh', 'empat puluh', 'lima puluh', 'enam puluh', 'tujuh puluh', 'delapan puluh', 'sembilan puluh'];
        $teens = ['sepuluh', 'sebelas', 'dua belas', 'tiga belas', 'empat belas', 'lima belas', 'enam belas', 'tujuh belas', 'delapan belas', 'sembilan belas'];

        $result = [];
        $number = str_pad($number, 3, '0', STR_PAD_LEFT);

        // Hundreds
        if ($number[0] != '0') {
            if ($number[0] == '1') {
                $result[] = 'seratus';
            } else {
                $result[] = $ones[$number[0]] . ' ratus';
            }
        }

        // Tens and ones
        if ($number[1] != '0') {
            if ($number[1] == '1') {
                $result[] = $teens[$number[2]];
            } else {
                $result[] = $tens[$number[1]];
                if ($number[2] != '0') {
                    $result[] = $ones[$number[2]];
                }
            }
        } else {
            if ($number[2] != '0') {
                $result[] = $ones[$number[2]];
            }
        }

        return implode(' ', $result);
    }
}
