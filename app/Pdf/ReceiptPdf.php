<?php

namespace App\Pdf;

use App\Models\Sale;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class ReceiptPdf
{
    protected $sale;
    protected $mpdf;
    protected $storeInfo;

    public function __construct(Sale $sale)
    {
        $this->sale = $sale;
        $this->storeInfo = [
            'name' => config('app.name', 'Toko Saya'),
            'address' => 'Jl. Contoh No. 123, Kota Contoh',
            'phone' => '(021) 12345678',
            'email' => 'info@tokosaya.com',
        ];
        $this->initializeMpdf();
    }

    protected function initializeMpdf(): void
    {
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables())->getDefaults();
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
            'fontDir' => array_merge($fontDirs, [
                resource_path('fonts'),
            ]),
            'fontdata' => $fontData + [
                'courier' => [
                    'R' => 'Courier New.ttf',
                    'B' => 'Courier New Bold.ttf',
                ],
            ],
            'default_font' => 'courier',
            'tempDir' => storage_path('app/mpdf/tmp'),
        ];

        $this->mpdf = new Mpdf($config);
    }

    public function generate(): string
    {
        $this->mpdf->WriteHTML($this->getHeader());
        $this->mpdf->WriteHTML($this->getStoreInfo());
        $this->mpdf->WriteHTML($this->getSaleInfo());
        $this->mpdf->WriteHTML($this->getItemsTable());
        $this->mpdf->WriteHTML($this->getPaymentInfo());
        $this->mpdf->WriteHTML($this->getFooter());

        return $this->mpdf->Output('', 'S');
    }

    public function download(string $filename = null): void
    {
        $filename = $filename ?? 'struk-' . $this->sale->invoice_number . '.pdf';
        $this->mpdf->Output($filename, 'D');
    }

    public function stream(): void
    {
        $this->mpdf->Output('struk-' . $this->sale->invoice_number . '.pdf', 'I');
    }

    protected function getHeader(): string
    {
        return '
        <div style="text-align: center; margin-bottom: 10px;">
            <h2 style="margin: 0; font-size: 16px; font-weight: bold;">' . $this->storeInfo['name'] . '</h2>
            <p style="margin: 2px 0; font-size: 10px;">' . $this->storeInfo['address'] . '</p>
            <p style="margin: 2px 0; font-size: 10px;">Telp: ' . $this->storeInfo['phone'] . '</p>
        </div>
        <hr style="border: none; border-top: 1px dashed #000; margin: 5px 0;">
        ';
    }

    protected function getStoreInfo(): string
    {
        return '
        <div style="text-align: center; margin-bottom: 10px;">
            <p style="margin: 2px 0; font-size: 10px;">' . now()->translatedFormat('l, d F Y H:i:s') . '</p>
            <p style="margin: 2px 0; font-size: 10px;">Kasir: ' . $this->sale->user->name . '</p>
        </div>
        ';
    }

    protected function getSaleInfo(): string
    {
        $customerInfo = $this->sale->customer
            ? 'Pelanggan: ' . $this->sale->customer->name
            : 'Pelanggan: Umum';

        return '
        <div style="margin-bottom: 10px;">
            <p style="margin: 2px 0; font-size: 10px;"><strong>No. Transaksi:</strong> ' . $this->sale->invoice_number . '</p>
            <p style="margin: 2px 0; font-size: 10px;">' . $customerInfo . '</p>
        </div>
        <hr style="border: none; border-top: 1px dashed #000; margin: 5px 0;">
        ';
    }

    protected function getItemsTable(): string
    {
        $html = '
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 10px;">
            <thead>
                <tr>
                    <th style="text-align: left; border-bottom: 1px dashed #000; padding: 2px 0;">Item</th>
                    <th style="text-align: right; border-bottom: 1px dashed #000; padding: 2px 0;">Qty</th>
                    <th style="text-align: right; border-bottom: 1px dashed #000; padding: 2px 0;">Harga</th>
                    <th style="text-align: right; border-bottom: 1px dashed #000; padding: 2px 0;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
        ';

        foreach ($this->sale->items as $item) {
            $html .= '
            <tr>
                <td style="padding: 2px 0; vertical-align: top;">' . $item->product->name . '</td>
                <td style="text-align: right; padding: 2px 0;">' . $item->quantity . '</td>
                <td style="text-align: right; padding: 2px 0;">' . number_format($item->unit_price, 0, ',', '.') . '</td>
                <td style="text-align: right; padding: 2px 0;">' . number_format($item->total_price, 0, ',', '.') . '</td>
            </tr>
            ';

            if ($item->discount > 0) {
                $html .= '
                <tr>
                    <td colspan="3" style="padding: 0 0 2px 10px; font-size: 9px;">Diskon</td>
                    <td style="text-align: right; padding: 0 0 2px 0; font-size: 9px;">-' . number_format($item->discount, 0, ',', '.') . '</td>
                </tr>
                ';
            }
        }

        $html .= '
            </tbody>
        </table>
        <hr style="border: none; border-top: 1px dashed #000; margin: 5px 0;">
        ';

        return $html;
    }

    protected function getPaymentInfo(): string
    {
        $paymentMethod = match ($this->sale->payment_method) {
            'cash' => 'Tunai',
            'transfer' => 'Transfer',
            'qris' => 'QRIS',
            'debit' => 'Kartu Debit',
            'credit' => 'Kartu Kredit',
            default => $this->sale->payment_method,
        };

        return '
        <div style="margin-bottom: 10px; font-size: 10px;">
            <table style="width: 100%;">
                <tr>
                    <td>Subtotal:</td>
                    <td style="text-align: right;">' . number_format($this->sale->subtotal, 0, ',', '.') . '</td>
                </tr>
                ' . ($this->sale->tax > 0 ? '
                <tr>
                    <td>Pajak (11%):</td>
                    <td style="text-align: right;">' . number_format($this->sale->tax, 0, ',', '.') . '</td>
                </tr>' : '') . '
                ' . ($this->sale->discount > 0 ? '
                <tr>
                    <td>Diskon:</td>
                    <td style="text-align: right;">-' . number_format($this->sale->discount, 0, ',', '.') . '</td>
                </tr>' : '') . '
                ' . ($this->sale->service_charge > 0 ? '
                <tr>
                    <td>Biaya Layanan:</td>
                    <td style="text-align: right;">' . number_format($this->sale->service_charge, 0, ',', '.') . '</td>
                </tr>' : '') . '
                <tr style="font-weight: bold;">
                    <td>Total:</td>
                    <td style="text-align: right;">' . number_format($this->sale->grand_total, 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td>Bayar (' . $paymentMethod . '):</td>
                    <td style="text-align: right;">' . number_format($this->sale->paid_amount, 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td>Kembali:</td>
                    <td style="text-align: right;">' . number_format($this->sale->change_amount, 0, ',', '.') . '</td>
                </tr>
            </table>
        </div>
        <hr style="border: none; border-top: 1px dashed #000; margin: 5px 0;">
        ';
    }

    protected function getFooter(): string
    {
        return '
        <div style="text-align: center; font-size: 9px; margin-top: 20px;">
            <p style="margin: 2px 0;">Terima kasih atas kunjungan Anda</p>
            <p style="margin: 2px 0;">Barang yang sudah dibeli tidak dapat ditukar atau dikembalikan</p>
            <p style="margin: 2px 0;">www.tokosaya.com</p>
        </div>
        ';
    }
}
