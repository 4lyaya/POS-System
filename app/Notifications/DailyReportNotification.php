<?php

namespace App\Notifications;

use App\Models\Sale;
use App\Models\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class DailyReportNotification extends Notification
{
    use Queueable;

    protected $date;
    protected $summary;

    public function __construct($date = null)
    {
        $this->date = $date ?? today();
        $this->summary = $this->getDailySummary();
    }

    protected function getDailySummary()
    {
        $sales = Sale::whereDate('sale_date', $this->date)->get();
        $purchases = Purchase::whereDate('purchase_date', $this->date)->get();

        return [
            'date' => $this->date->format('d/m/Y'),
            'total_sales' => $sales->sum('grand_total'),
            'total_transactions' => $sales->count(),
            'total_purchases' => $purchases->sum('grand_total'),
            'total_purchase_transactions' => $purchases->count(),
            'average_sale' => $sales->avg('grand_total'),
            'top_cashier' => $this->getTopCashier($sales),
        ];
    }

    protected function getTopCashier($sales)
    {
        if ($sales->isEmpty()) {
            return null;
        }

        $cashierSales = $sales->groupBy('user_id')->map(function ($group) {
            return [
                'user' => $group->first()->user,
                'total' => $group->sum('grand_total'),
                'count' => $group->count(),
            ];
        })->sortByDesc('total')->first();

        return $cashierSales;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $topCashierInfo = $this->summary['top_cashier']
            ? $this->summary['top_cashier']['user']->name . ' (Rp ' . number_format($this->summary['top_cashier']['total'], 0, ',', '.') . ' - ' . $this->summary['top_cashier']['count'] . ' transaksi)'
            : 'Tidak ada transaksi';

        return (new MailMessage)
            ->subject('��� Laporan Harian - ' . $this->summary['date'])
            ->greeting('Laporan Harian Sistem POS')
            ->line('**Tanggal:** ' . $this->summary['date'])
            ->line('')
            ->line('**��� Penjualan:**')
            ->line('- Total Penjualan: Rp ' . number_format($this->summary['total_sales'], 0, ',', '.'))
            ->line('- Total Transaksi: ' . number_format($this->summary['total_transactions'], 0, ',', '.'))
            ->line('- Rata-rata Transaksi: Rp ' . number_format($this->summary['average_sale'], 0, ',', '.'))
            ->line('- Kasir Terbaik: ' . $topCashierInfo)
            ->line('')
            ->line('**��� Pembelian:**')
            ->line('- Total Pembelian: Rp ' . number_format($this->summary['total_purchases'], 0, ',', '.'))
            ->line('- Total Transaksi: ' . number_format($this->summary['total_purchase_transactions'], 0, ',', '.'))
            ->line('')
            ->action('Lihat Dashboard', url('/dashboard'))
            ->line('Laporan ini dibuat otomatis setiap hari.');
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Laporan Harian ' . $this->summary['date'],
            'message' => 'Total penjualan: Rp ' . number_format($this->summary['total_sales'], 0, ',', '.') . ' dari ' . $this->summary['total_transactions'] . ' transaksi',
            'date' => $this->summary['date'],
            'total_sales' => $this->summary['total_sales'],
            'total_transactions' => $this->summary['total_transactions'],
        ];
    }
}
