<?php

namespace App\Pdf;

use App\Models\Purchase;
use Mpdf\Mpdf;

class InvoicePdf
{
    protected $purchase;
    protected $mpdf;
    protected $storeInfo;

    public function __construct(Purchase $purchase)
    {
        $this->purchase = $purchase;
        $this->storeInfo = [
            'name' => config('app.name', 'Toko POS System'),
            'address' => 'Jl. Contoh No. 123, Kota Contoh',
            'phone' => '(021) 12345678',
            'email' => 'info@tokosaya.com',
        ];
        $this->initializeMpdf();
    }

    protected function initializeMpdf(): void
    {
        $config = [
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 5,
            'margin_footer' => 5,
            'default_font' => 'dejavusans',
            'tempDir' => storage_path('app/mpdf/tmp'),
        ];

        $this->mpdf = new Mpdf($config);
        $this->mpdf->SetHeader('|Invoice Pembelian|');
        $this->mpdf->SetFooter('{PAGENO}');
    }

    public function generate(): string
    {
        $this->mpdf->WriteHTML($this->getHeader());
        $this->mpdf->WriteHTML($this->getInvoiceInfo());
        $this->mpdf->WriteHTML($this->getSupplierInfo());
        $this->mpdf->WriteHTML($this->getItemsTable());
        $this->mpdf->WriteHTML($this->getPaymentInfo());
        $this->mpdf->WriteHTML($this->getFooter());

        return $this->mpdf->Output('', 'S');
    }

    public function download(string $filename = null): void
    {
        $filename = $filename ?? 'invoice-' . $this->purchase->invoice_number . '.pdf';
        $this->mpdf->Output($filename, 'D');
    }

    public function stream(): void
    {
        $this->mpdf->Output('invoice-' . $this->purchase->invoice_number . '.pdf', 'I');
    }

    protected function getHeader(): string
    {
        return '
        <div style="text-align: center; margin-bottom: 20px;">
            <h1 style="margin: 0; color: #333;">' . $this->storeInfo['name'] . '</h1>
            <p style="margin: 5px 0; color: #666;">' . $this->storeInfo['address'] . '</p>
            <p style="margin: 5px 0; color: #666;">Telp: ' . $this->storeInfo['phone'] . ' | Email: ' . $this->storeInfo['email'] . '</p>
            <h2 style="margin: 10px 0; color: #555; border-bottom: 2px solid #007bff; padding-bottom: 5px;">INVOICE PEMBELIAN</h2>
        </div>
        ';
    }

    protected function getInvoiceInfo(): string
    {
        return '
        <div style="margin-bottom: 20px;">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 50%;">
                        <strong>No. Invoice:</strong> ' . $this->purchase->invoice_number . '<br>
                        <strong>Tanggal:</strong> ' . $this->purchase->purchase_date->format('d/m/Y') . '<br>
                        <strong>Dibuat Oleh:</strong> ' . $this->purchase->user->name . '
                    </td>
                    <td style="width: 50%; text-align: right;">
                        <strong>Status:</strong> ' . ucfirst($this->purchase->payment_status) . '<br>
                        <strong>Metode Bayar:</strong> ' . ucfirst($this->purchase->payment_method) . '<br>
                        ' . ($this->purchase->due_date ? '<strong>Jatuh Tempo:</strong> ' . $this->purchase->due_date->format('d/m/Y') . '' : '') . '
                    </td>
                </tr>
            </table>
        </div>
        ';
    }

    protected function getSupplierInfo(): string
    {
        if (!$this->purchase->supplier) {
            return '';
        }

        return '
        <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <h3 style="margin: 0 0 10px 0; color: #555;">Supplier</h3>
            <table style="width: 100%;">
                <tr>
                    <td style="width: 50%;">
                        <strong>Nama:</strong> ' . $this->purchase->supplier->name . '<br>
                        <strong>Alamat:</strong> ' . ($this->purchase->supplier->address ?? '-') . '<br>
                    </td>
                    <td style="width: 50%;">
                        <strong>Telepon:</strong> ' . ($this->purchase->supplier->phone ?? '-') . '<br>
                        <strong>Email:</strong> ' . ($this->purchase->supplier->email ?? '-') . '<br>
                    </td>
                </tr>
            </table>
        </div>
        ';
    }

    protected function getItemsTable(): string
    {
        $html = '
        <div style="margin-bottom: 20px;">
            <h3 style="color: #555; margin-bottom: 10px;">Detail Barang</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                <thead>
                    <tr style="background: #343a40; color: white;">
                        <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">No</th>
                        <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Kode</th>
                        <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Nama Barang</th>
                        <th style="padding: 8px; border: 1px solid #dee2e6; text-align: right;">Qty</th>
                        <th style="padding: 8px; border: 1px solid #dee2e6; text-align: right;">Harga Satuan</th>
                        <th style="padding: 8px; border: 1px solid #dee2e6; text-align: right;">Diskon</th>
                        <th style="padding: 8px; border: 1px solid #dee2e6; text-align: right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
        ';

        $counter = 1;
        foreach ($this->purchase->items as $item) {
            $html .= '
            <tr>
                <td style="padding: 8px; border: 1px solid #dee2e6;">' . $counter++ . '</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">' . $item->product->code . '</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">' . $item->product->name . '</td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right;">' . number_format($item->quantity, 0, ',', '.') . '</td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right;">' . number_format($item->unit_price, 0, ',', '.') . '</td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right;">' . number_format($item->discount, 0, ',', '.') . '</td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right;">' . number_format($item->total_price, 0, ',', '.') . '</td>
            </tr>
            ';
        }

        $html .= '
                </tbody>
            </table>
        </div>
        ';

        return $html;
    }

    protected function getPaymentInfo(): string
    {
        return '
        <div style="margin-bottom: 20px;">
            <table style="width: 100%; font-size: 12px;">
                <tr>
                    <td style="width: 70%;"></td>
                    <td style="width: 30%;">
                        <table style="width: 100%;">
                            <tr>
                                <td>Subtotal:</td>
                                <td style="text-align: right;">' . number_format($this->purchase->subtotal, 0, ',', '.') . '</td>
                            </tr>
                            ' . ($this->purchase->tax > 0 ? '
                            <tr>
                                <td>Pajak:</td>
                                <td style="text-align: right;">' . number_format($this->purchase->tax, 0, ',', '.') . '</td>
                            </tr>' : '') . '
                            ' . ($this->purchase->discount > 0 ? '
                            <tr>
                                <td>Diskon:</td>
                                <td style="text-align: right;">-' . number_format($this->purchase->discount, 0, ',', '.') . '</td>
                            </tr>' : '') . '
                            ' . ($this->purchase->shipping_cost > 0 ? '
                            <tr>
                                <td>Biaya Kirim:</td>
                                <td style="text-align: right;">' . number_format($this->purchase->shipping_cost, 0, ',', '.') . '</td>
                            </tr>' : '') . '
                            <tr style="font-weight: bold; font-size: 14px;">
                                <td>TOTAL:</td>
                                <td style="text-align: right;">' . number_format($this->purchase->grand_total, 0, ',', '.') . '</td>
                            </tr>
                            <tr>
                                <td>Dibayar:</td>
                                <td style="text-align: right;">' . number_format($this->purchase->paid_amount, 0, ',', '.') . '</td>
                            </tr>
                            <tr>
                                <td>Sisa Hutang:</td>
                                <td style="text-align: right; color: #dc3545; font-weight: bold;">' . number_format($this->purchase->due_amount, 0, ',', '.') . '</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        ';
    }

    protected function getFooter(): string
    {
        $notes = $this->purchase->notes ? '<p><strong>Catatan:</strong> ' . nl2br($this->purchase->notes) . '</p>' : '';

        return '
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 10px;">
            ' . $notes . '
            <p style="text-align: center; color: #666;">
                Invoice ini dibuat secara otomatis oleh sistem.<br>
                Terima kasih atas kerjasamanya.
            </p>
            <div style="margin-top: 30px;">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 50%; text-align: center;">
                            <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto; padding-top: 5px;">
                                Supplier
                            </div>
                        </td>
                        <td style="width: 50%; text-align: center;">
                            <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto; padding-top: 5px;">
                                Penerima
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        ';
    }
}
