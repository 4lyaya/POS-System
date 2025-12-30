<?php

namespace App\Notifications;

use App\Models\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentDueNotification extends Notification
{
    use Queueable;

    protected $purchase;
    protected $daysUntilDue;

    public function __construct(Purchase $purchase, $daysUntilDue = 0)
    {
        $this->purchase = $purchase;
        $this->daysUntilDue = $daysUntilDue;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $subject = $this->daysUntilDue <= 0
            ? '⚠️ Pembayaran Jatuh Tempo Hari Ini - ' . $this->purchase->invoice_number
            : '⏰ Peringatan Jatuh Tempo Pembayaran - ' . $this->purchase->invoice_number;

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Halo ' . $notifiable->name . '!')
            ->line('Pembayaran pembelian akan segera jatuh tempo.');

        if ($this->daysUntilDue <= 0) {
            $message->line('**⚠️ PEMBAYARAN JATUH TEMPO HARI INI!**');
        } else {
            $message->line('Pembayaran akan jatuh tempo dalam **' . $this->daysUntilDue . ' hari**.');
        }

        $message->line('**Detail Pembelian:**')
            ->line('- No. Invoice: ' . $this->purchase->invoice_number)
            ->line('- Supplier: ' . ($this->purchase->supplier ? $this->purchase->supplier->name : 'Tidak ada'))
            ->line('- Tanggal Jatuh Tempo: ' . ($this->purchase->due_date ? $this->purchase->due_date->format('d/m/Y') : 'Tidak ditentukan'))
            ->line('- Total Hutang: Rp ' . number_format($this->purchase->due_amount, 0, ',', '.'))
            ->line('- Total Pembelian: Rp ' . number_format($this->purchase->grand_total, 0, ',', '.'))
            ->action('Lihat Detail & Bayar', url('/purchases/' . $this->purchase->id))
            ->line('Silakan segera lakukan pembayaran untuk menghindari keterlambatan.');

        return $message;
    }

    public function toArray($notifiable)
    {
        $urgency = $this->daysUntilDue <= 0 ? 'danger' : ($this->daysUntilDue <= 3 ? 'warning' : 'info');
        $urgencyText = $this->daysUntilDue <= 0 ? 'Jatuh Tempo' : ($this->daysUntilDue . ' hari lagi');

        return [
            'title' => 'Pembayaran Jatuh Tempo: ' . $this->purchase->invoice_number,
            'message' => 'Pembayaran pembelian dari ' . ($this->purchase->supplier ? $this->purchase->supplier->name : 'supplier') . ' sebesar Rp ' . number_format($this->purchase->due_amount, 0, ',', '.') . ' akan jatuh tempo ' . $urgencyText,
            'icon' => 'fa-clock',
            'color' => $urgency,
            'url' => '/purchases/' . $this->purchase->id,
            'purchase_id' => $this->purchase->id,
            'invoice_number' => $this->purchase->invoice_number,
            'due_amount' => $this->purchase->due_amount,
            'due_date' => $this->purchase->due_date ? $this->purchase->due_date->toDateString() : null,
            'days_until_due' => $this->daysUntilDue,
            'supplier' => $this->purchase->supplier ? $this->purchase->supplier->name : null,
        ];
    }
}
