<?php

namespace App\Notifications;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class NewSaleNotification extends Notification
{
    use Queueable;

    protected $sale;

    public function __construct(Sale $sale)
    {
        $this->sale = $sale;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('��� Transaksi Baru - ' . $this->sale->invoice_number)
            ->greeting('Halo ' . $notifiable->name . '!')
            ->line('Transaksi baru telah diproses.')
            ->line('**Detail Transaksi:**')
            ->line('- No. Invoice: ' . $this->sale->invoice_number)
            ->line('- Tanggal: ' . $this->sale->sale_date->format('d/m/Y H:i'))
            ->line('- Customer: ' . ($this->sale->customer ? $this->sale->customer->name : 'Umum'))
            ->line('- Total: Rp ' . number_format($this->sale->grand_total, 0, ',', '.'))
            ->line('- Kasir: ' . $this->sale->user->name)
            ->action('Lihat Detail', url('/sales/' . $this->sale->id))
            ->line('Terima kasih!');
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Transaksi Baru: ' . $this->sale->invoice_number,
            'message' => 'Transaksi sebesar Rp ' . number_format($this->sale->grand_total, 0, ',', '.') . ' telah diproses oleh ' . $this->sale->user->name,
            'icon' => 'fa-shopping-cart',
            'color' => 'success',
            'url' => '/sales/' . $this->sale->id,
            'sale_id' => $this->sale->id,
            'invoice_number' => $this->sale->invoice_number,
            'amount' => $this->sale->grand_total,
            'cashier' => $this->sale->user->name,
        ];
    }
}
