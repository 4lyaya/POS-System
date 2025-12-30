<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    public static function formatDate($date, $format = 'd/m/Y')
    {
        if (!$date) {
            return '-';
        }
        
        return Carbon::parse($date)->format($format);
    }
    
    public static function formatDateTime($date, $format = 'd/m/Y H:i')
    {
        if (!$date) {
            return '-';
        }
        
        return Carbon::parse($date)->format($format);
    }
    
    public static function getIndonesianMonth($month = null)
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
        
        if ($month === null) {
            return $months;
        }
        
        return $months[$month] ?? $month;
    }
    
    public static function getIndonesianDay($day = null)
    {
        $days = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];
        
        if ($day === null) {
            return $days;
        }
        
        return $days[$day] ?? $day;
    }
    
    public static function getDateRange($period = 'month')
    {
        $today = Carbon::today();
        
        switch ($period) {
            case 'today':
                return [
                    'start' => $today,
                    'end' => $today,
                    'label' => 'Hari Ini',
                ];
            case 'yesterday':
                return [
                    'start' => $today->copy()->subDay(),
                    'end' => $today->copy()->subDay(),
                    'label' => 'Kemarin',
                ];
            case 'week':
                return [
                    'start' => $today->copy()->startOfWeek(),
                    'end' => $today->copy()->endOfWeek(),
                    'label' => 'Minggu Ini',
                ];
            case 'month':
                return [
                    'start' => $today->copy()->startOfMonth(),
                    'end' => $today->copy()->endOfMonth(),
                    'label' => 'Bulan Ini',
                ];
            case 'last_month':
                return [
                    'start' => $today->copy()->subMonth()->startOfMonth(),
                    'end' => $today->copy()->subMonth()->endOfMonth(),
                    'label' => 'Bulan Lalu',
                ];
            case 'year':
                return [
                    'start' => $today->copy()->startOfYear(),
                    'end' => $today->copy()->endOfYear(),
                    'label' => 'Tahun Ini',
                ];
            default:
                return [
                    'start' => $today->copy()->startOfMonth(),
                    'end' => $today->copy()->endOfMonth(),
                    'label' => 'Bulan Ini',
                ];
        }
    }
}
