<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class LowStockNotification extends Notification
{
    use Queueable;

    protected $product;
    protected $stockLevel;

    public function __construct(Product $product)
    {
        $this->product = $product;
        $this->stockLevel = $product->stock;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('⚠️ Peringatan Stok Menipis - ' . $this->product->name)
            ->greeting('Halo ' . $notifiable->name . '!')
            ->line('Stok produk **' . $this->product->name . '** sedang menipis.')
            ->line('**Detail Produk:**')
            ->line('- Kode: ' . $this->product->code)
            ->line('- Stok Saat Ini: ' . $this->stockLevel)
            ->line('- Stok Minimum: ' . $this->product->min_stock)
            ->action('Lihat Produk', url('/products/' . $this->product->id))
            ->line('Silakan lakukan restok segera.')
            ->salutation('Salam, Sistem POS');
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Stok Menipis: ' . $this->product->name,
            'message' => 'Stok ' . $this->product->name . ' tersisa ' . $this->stockLevel . ' unit. Minimum: ' . $this->product->min_stock . ' unit.',
            'icon' => 'fa-exclamation-triangle',
            'color' => 'warning',
            'url' => '/products/' . $this->product->id,
            'product_id' => $this->product->id,
            'stock_level' => $this->stockLevel,
            'min_stock' => $this->product->min_stock,
        ];
    }
}
