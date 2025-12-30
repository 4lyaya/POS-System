<?php

namespace App\Services\POS;

use App\Models\Sale;
use App\Models\Setting;
use Mpdf\Mpdf;

class ReceiptService
{
    protected $sale;
    protected $storeSettings;

    public function __construct(Sale $sale = null)
    {
        $this->sale = $sale;
        $this->loadStoreSettings();
    }

    protected function loadStoreSettings()
    {
        $this->storeSettings = [
            'name' => Setting::getValue('store_name', 'Toko POS System'),
            'address' => Setting::getValue('store_address', 'Jl. Contoh No. 123'),
            'phone' => Setting::getValue('store_phone', '(021) 12345678'),
            'email' => Setting::getValue('store_email', 'info@tokopos.com'),
            'footer' => Setting::getValue('receipt_footer', 'Terima kasih atas kunjungan Anda'),
        ];
    }

    public function generateReceipt(Sale $sale)
    {
        $this->sale = $sale;
        $sale->load(['customer', 'user', 'items.product']);

        $mpdf = $this->initializeMpdf();

        $html = $this->generateReceiptHtml();

        $mpdf->WriteHTML($html);

        $filename = 'receipt-' . $sale->invoice_number . '.pdf';
        $path = storage_path('app/public/receipts/' . $filename);

        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $mpdf->Output($path, 'F');

        return $filename;
    }

    public function streamReceipt()
    {
        if (!$this->sale) {
            throw new \Exception('Sale not set');
        }

        $this->sale->load(['customer', 'user', 'items.product']);

        $mpdf = $this->initializeMpdf();
        $html = $this->generateReceiptHtml();

        $mpdf->WriteHTML($html);

        $filename = 'receipt-' . $this->sale->invoice_number . '.pdf';

        return $mpdf->Output($filename, 'I');
    }

    public function downloadReceipt()
    {
        if (!$this->sale) {
            throw new \Exception('Sale not set');
        }

        $this->sale->load(['customer', 'user', 'items.product']);

        $mpdf = $this->initializeMpdf();
        $html = $this->generateReceiptHtml();

        $mpdf->WriteHTML($html);

        $filename = 'receipt-' . $this->sale->invoice_number . '.pdf';

        return $mpdf->Output($filename, 'D');
    }

    protected function initializeMpdf()
    {
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $config = [
            'mode' => 'utf-8',
            'format' => [80, 297], // Thermal paper size (80mm width)
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_header' => 0,
            'margin_footer' => 0,
            'default_font' => 'courier',
            'fontDir' => array_merge($fontDirs, [
                resource_path('fonts'),
            ]),
            'fontdata' => $fontData + [
                'courier' => [
                    'R' => 'Courier New.ttf',
                    'B' => 'Courier New Bold.ttf',
                ],
                'dejavusans' => [
                    'R' => 'DejaVuSans.ttf',
                    'B' => 'DejaVuSans-Bold.ttf',
                ],
            ],
            'tempDir' => storage_path('app/mpdf/tmp'),
        ];

        return new Mpdf($config);
    }

    protected function generateReceiptHtml()
    {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: DejaVuSans, sans-serif; font-size: 10px; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .text-bold { font-weight: bold; }
                .text-large { font-size: 12px; }
                .text-small { font-size: 8px; }
                .border-top { border-top: 1px dashed #000; }
                .border-bottom { border-bottom: 1px dashed #000; }
                .mt-1 { margin-top: 4px; }
                .mb-1 { margin-bottom: 4px; }
                .py-1 { padding-top: 4px; padding-bottom: 4px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 2px 0; }
            </style>
        </head>
        <body>';

        // Store header
        $html .= '
        <div class="text-center mb-1">
            <div class="text-large text-bold">' . htmlspecialchars($this->storeSettings['name']) . '</div>
            <div>' . htmlspecialchars($this->storeSettings['address']) . '</div>
            <div>Telp: ' . htmlspecialchars($this->storeSettings['phone']) . '</div>
        </div>
        <div class="border-top py-1"></div>';

        // Receipt info
        $html .= '
        <div class="mb-1">
            <div>No: ' . $this->sale->invoice_number . '</div>
            <div>Tanggal: ' . $this->sale->sale_date->format('d/m/Y H:i:s') . '</div>
            <div>Kasir: ' . htmlspecialchars($this->sale->user->name) . '</div>
            <div>Customer: ' . htmlspecialchars($this->sale->customer ? $this->sale->customer->name : 'Umum') . '</div>
        </div>
        <div class="border-top py-1"></div>';

        // Items table
        $html .= '
        <table class="mb-1">
            <thead>
                <tr class="border-bottom">
                    <th style="text-align: left;">Item</th>
                    <th style="text-align: right;">Qty</th>
                    <th style="text-align: right;">Harga</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($this->sale->items as $item) {
            $productName = htmlspecialchars($item->product->name);
            if (strlen($productName) > 20) {
                $productName = substr($productName, 0, 17) . '...';
            }

            $html .= '
            <tr>
                <td>' . $productName . '</td>
                <td class="text-right">' . $item->quantity . '</td>
                <td class="text-right">' . number_format($item->unit_price, 0, ',', '.') . '</td>
                <td class="text-right">' . number_format($item->total_price, 0, ',', '.') . '</td>
            </tr>';

            if ($item->discount > 0) {
                $html .= '
                <tr>
                    <td colspan="3" style="padding-left: 10px;">Diskon</td>
                    <td class="text-right">-' . number_format($item->discount, 0, ',', '.') . '</td>
                </tr>';
            }
        }

        $html .= '
            </tbody>
        </table>
        <div class="border-top py-1"></div>';

        // Summary
        $paymentMethod = $this->getPaymentMethodLabel($this->sale->payment_method);

        $html .= '
        <div class="mb-1">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-right">' . number_format($this->sale->subtotal, 0, ',', '.') . '</td>
                </tr>';

        if ($this->sale->tax > 0) {
            $html .= '
                <tr>
                    <td>Pajak:</td>
                    <td class="text-right">' . number_format($this->sale->tax, 0, ',', '.') . '</td>
                </tr>';
        }

        if ($this->sale->discount > 0) {
            $html .= '
                <tr>
                    <td>Diskon:</td>
                    <td class="text-right">-' . number_format($this->sale->discount, 0, ',', '.') . '</td>
                </tr>';
        }

        if ($this->sale->service_charge > 0) {
            $html .= '
                <tr>
                    <td>Biaya Layanan:</td>
                    <td class="text-right">' . number_format($this->sale->service_charge, 0, ',', '.') . '</td>
                </tr>';
        }

        $html .= '
                <tr class="text-bold">
                    <td>TOTAL:</td>
                    <td class="text-right">' . number_format($this->sale->grand_total, 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td>Bayar (' . $paymentMethod . '):</td>
                    <td class="text-right">' . number_format($this->sale->paid_amount, 0, ',', '.') . '</td>
                </tr>';

        if ($this->sale->change_amount > 0) {
            $html .= '
                <tr>
                    <td>Kembali:</td>
                    <td class="text-right">' . number_format($this->sale->change_amount, 0, ',', '.') . '</td>
                </tr>';
        }

        $html .= '
            </table>
        </div>
        <div class="border-top py-1"></div>';

        // Footer
        $html .= '
        <div class="text-center text-small mt-1">
            <div>' . htmlspecialchars($this->storeSettings['footer']) . '</div>
            <div>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan</div>
            <div>www.' . strtolower(str_replace(' ', '', $this->storeSettings['name'])) . '.com</div>
        </div>';

        $html .= '
        </body>
        </html>';

        return $html;
    }

    protected function getPaymentMethodLabel($method)
    {
        return match ($method) {
            'cash' => 'Tunai',
            'transfer' => 'Transfer',
            'qris' => 'QRIS',
            'debit' => 'Kartu Debit',
            'credit' => 'Kartu Kredit',
            default => ucfirst($method),
        };
    }

    public function generateInvoicePdf(Sale $sale, $format = 'A4')
    {
        $this->sale = $sale;
        $sale->load(['customer', 'user', 'items.product']);

        $config = [
            'mode' => 'utf-8',
            'format' => $format,
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'default_font' => 'dejavusans',
            'tempDir' => storage_path('app/mpdf/tmp'),
        ];

        $mpdf = new Mpdf($config);

        $html = $this->generateInvoiceHtml();

        $mpdf->WriteHTML($html);

        $filename = 'invoice-' . $sale->invoice_number . '.pdf';

        return [
            'content' => $mpdf->Output('', 'S'),
            'filename' => $filename,
        ];
    }

    protected function generateInvoiceHtml()
    {
        // Similar to receipt but with more details and A4 format
        // Implementation can be extended as needed
        return $this->generateReceiptHtml();
    }
}
