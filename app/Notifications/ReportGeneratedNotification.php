<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Support\Facades\Storage;

class ReportGeneratedNotification extends Notification
{
    use Queueable;

    protected $reportType;
    protected $reportPeriod;
    protected $filePath;
    protected $fileName;
    protected $user;
    protected $downloadUrl;
    protected $reportData;

    public function __construct($reportType, $reportPeriod, $filePath, $fileName, $user = null, $reportData = [])
    {
        $this->reportType = $reportType;
        $this->reportPeriod = $reportPeriod;
        $this->filePath = $filePath;
        $this->fileName = $fileName;
        $this->user = $user ?? auth()->user();
        $this->reportData = $reportData;

        // Generate download URL
        $this->downloadUrl = route('reports.download', [
            'file' => $fileName,
            'token' => $this->generateDownloadToken()
        ]);
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        $mail = (new MailMessage)
            ->subject('��� ' . $this->getReportTitle() . ' - ' . $this->reportPeriod)
            ->greeting('Halo ' . $notifiable->name . '!')
            ->line('Laporan yang Anda minta telah selesai dibuat.')
            ->line('**Detail Laporan:**')
            ->line('- Jenis: ' . $this->getReportTypeLabel())
            ->line('- Periode: ' . $this->reportPeriod)
            ->line('- Dibuat: ' . now()->translatedFormat('d F Y H:i:s'))
            ->line('- Format: ' . strtoupper(pathinfo($this->fileName, PATHINFO_EXTENSION)));

        // Add report summary if available
        if (!empty($this->reportData['summary'])) {
            $mail->line('')
                ->line('**Ringkasan Laporan:**');

            foreach ($this->reportData['summary'] as $key => $value) {
                $label = $this->getSummaryLabel($key);
                $formattedValue = $this->formatSummaryValue($key, $value);
                $mail->line('- ' . $label . ': ' . $formattedValue);
            }
        }

        $mail->action('��� Download Laporan', $this->downloadUrl)
            ->line('Laporan akan tersedia selama 24 jam.')
            ->line('Jika Anda tidak meminta laporan ini, abaikan pesan ini.')
            ->salutation('Salam, Sistem POS');

        // Attach file if it exists
        if (Storage::exists($this->filePath)) {
            $mail->attach(storage_path('app/' . $this->filePath), [
                'as' => $this->fileName,
                'mime' => $this->getMimeType($this->fileName),
            ]);
        }

        return $mail;
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Laporan ' . $this->getReportTypeLabel() . ' Siap',
            'message' => 'Laporan ' . $this->getReportTypeLabel() . ' periode ' . $this->reportPeriod . ' telah selesai dibuat.',
            'icon' => $this->getReportIcon(),
            'color' => 'success',
            'url' => $this->downloadUrl,
            'report_type' => $this->reportType,
            'report_period' => $this->reportPeriod,
            'file_name' => $this->fileName,
            'file_size' => $this->getFileSize(),
            'generated_by' => $this->user->name,
            'generated_at' => now()->toDateTimeString(),
            'download_url' => $this->downloadUrl,
            'summary' => $this->reportData['summary'] ?? null,
        ];
    }

    public function toDatabase($notifiable)
    {
        return new DatabaseMessage([
            'title' => 'Laporan ' . $this->getReportTypeLabel() . ' Siap',
            'message' => 'Laporan ' . $this->getReportTypeLabel() . ' periode ' . $this->reportPeriod . ' telah selesai dibuat.',
            'icon' => $this->getReportIcon(),
            'color' => 'success',
            'url' => $this->downloadUrl,
            'type' => 'report_generated',
            'data' => [
                'report_type' => $this->reportType,
                'report_period' => $this->reportPeriod,
                'file_name' => $this->fileName,
                'file_size' => $this->getFileSize(),
                'generated_by' => $this->user->name,
                'generated_at' => now()->toDateTimeString(),
                'download_url' => $this->downloadUrl,
                'summary' => $this->reportData['summary'] ?? null,
            ],
        ]);
    }

    protected function getReportTitle()
    {
        return match ($this->reportType) {
            'sales' => 'Laporan Penjualan',
            'purchases' => 'Laporan Pembelian',
            'stock' => 'Laporan Stok',
            'inventory' => 'Laporan Inventory',
            'financial' => 'Laporan Keuangan',
            'products' => 'Laporan Produk',
            'customers' => 'Laporan Pelanggan',
            'daily' => 'Laporan Harian',
            'monthly' => 'Laporan Bulanan',
            'yearly' => 'Laporan Tahunan',
            default => 'Laporan Sistem',
        };
    }

    protected function getReportTypeLabel()
    {
        return match ($this->reportType) {
            'sales' => 'Penjualan',
            'purchases' => 'Pembelian',
            'stock' => 'Stok',
            'inventory' => 'Inventory',
            'financial' => 'Keuangan',
            'products' => 'Produk',
            'customers' => 'Pelanggan',
            default => ucfirst($this->reportType),
        };
    }

    protected function getReportIcon()
    {
        return match ($this->reportType) {
            'sales' => 'fa-chart-line',
            'purchases' => 'fa-shopping-cart',
            'stock' => 'fa-boxes',
            'inventory' => 'fa-warehouse',
            'financial' => 'fa-money-bill-wave',
            'products' => 'fa-tags',
            'customers' => 'fa-users',
            default => 'fa-file-alt',
        };
    }

    protected function getSummaryLabel($key)
    {
        $labels = [
            'total_sales' => 'Total Penjualan',
            'total_transactions' => 'Total Transaksi',
            'total_items' => 'Total Item Terjual',
            'average_transaction' => 'Rata-rata Transaksi',
            'total_purchases' => 'Total Pembelian',
            'total_purchase_transactions' => 'Transaksi Pembelian',
            'total_profit' => 'Total Keuntungan',
            'total_expenses' => 'Total Pengeluaran',
            'net_income' => 'Pendapatan Bersih',
            'profit_margin' => 'Margin Keuntungan',
            'total_products' => 'Total Produk',
            'total_stock' => 'Total Stok',
            'stock_value' => 'Nilai Stok',
            'low_stock_count' => 'Stok Menipis',
            'out_of_stock_count' => 'Stok Habis',
            'start_date' => 'Tanggal Mulai',
            'end_date' => 'Tanggal Akhir',
            'period' => 'Periode',
        ];

        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    protected function formatSummaryValue($key, $value)
    {
        if (is_numeric($value)) {
            // Format currency values
            if (str_contains($key, ['sales', 'purchases', 'profit', 'expenses', 'income', 'value', 'price', 'amount', 'total'])) {
                return 'Rp ' . number_format($value, 0, ',', '.');
            }

            // Format percentage values
            if (str_contains($key, ['margin', 'rate', 'percentage'])) {
                return number_format($value, 2) . '%';
            }

            // Format integer values
            if (is_int($value) || str_contains($key, ['count', 'quantity', 'items', 'transactions'])) {
                return number_format($value, 0, ',', '.');
            }

            // Format decimal values
            return number_format($value, 2, ',', '.');
        }

        // Format date values
        if (str_contains($key, ['date', 'period']) && strtotime($value)) {
            return date('d/m/Y', strtotime($value));
        }

        return $value;
    }

    protected function getFileSize()
    {
        if (Storage::exists($this->filePath)) {
            $size = Storage::size($this->filePath);

            if ($size < 1024) {
                return $size . ' B';
            } elseif ($size < 1048576) {
                return round($size / 1024, 2) . ' KB';
            } else {
                return round($size / 1048576, 2) . ' MB';
            }
        }

        return '0 B';
    }

    protected function getMimeType($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }

    protected function generateDownloadToken()
    {
        return hash('sha256', $this->fileName . now()->timestamp . config('app.key'));
    }

    public function getDownloadUrl()
    {
        return $this->downloadUrl;
    }

    public function getFilePath()
    {
        return $this->filePath;
    }

    public function getFileName()
    {
        return $this->fileName;
    }

    public function getReportType()
    {
        return $this->reportType;
    }

    public function getReportPeriod()
    {
        return $this->reportPeriod;
    }
}
