<?php

namespace App\Notifications;

use App\Models\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class PurchaseOrderNotification extends Notification
{
    use Queueable;

    protected $purchase;

    public function __construct(Purchase $purchase)
    {
        $this->purchase = $purchase;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('��� Pesanan Pembelian Baru - ' . $this->purchase->invoice_number)
            ->greeting('Halo ' . $notifiable->name . '!')
            ->line('Pesanan pembelian baru telah dibuat.')
            ->line('**Detail Pesanan:**')
            ->line('- No. Invoice: ' . $this->purchase->invoice_number)
            ->line('- Supplier: ' . ($this->purchase->supplier ? $this->purchase->supplier->name : 'Tidak ada'))
            ->line('- Tanggal: ' . $this->purchase->purchase_date->format('d/m/Y'))
            ->line('- Total: Rp ' . number_format($this->purchase->grand_total, 0, ',', '.'))
            ->line('- Status: ' . ucfirst($this->purchase->payment_status))
            ->action('Lihat Detail', url('/purchases/' . $this->purchase->id))
            ->line('Silakan lakukan pembayaran atau konfirmasi dengan supplier.');
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Pesanan Pembelian Baru: ' . $this->purchase->invoice_number,
            'message' => 'Pesanan pembelian dari ' . ($this->purchase->supplier ? $this->purchase->supplier->name : 'supplier') . ' sebesar Rp ' . number_format($this->purchase->grand_total, 0, ',', '.'),
            'icon' => 'fa-shopping-bag',
            'color' => 'info',
            'url' => '/purchases/' . $this->purchase->id,
            'purchase_id' => $this->purchase->id,
            'invoice_number' => $this->purchase->invoice_number,
            'amount' => $this->purchase->grand_total,
            'supplier' => $this->purchase->supplier ? $this->purchase->supplier->name : null,
        ];
    }
}
