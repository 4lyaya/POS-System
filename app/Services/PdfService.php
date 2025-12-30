<?php

namespace App\Services;

use Mpdf\Mpdf;
use Illuminate\Support\Facades\View;

class PdfService
{
    protected $mpdf;
    protected $config;

    public function __construct($config = [])
    {
        $defaultConfig = [
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

        $this->config = array_merge($defaultConfig, $config);
        $this->mpdf = new Mpdf($this->config);
    }

    public function generateFromView($view, $data = [], $options = [])
    {
        $html = View::make($view, $data)->render();

        // Apply options
        if (isset($options['header'])) {
            $this->mpdf->SetHeader($options['header']);
        }

        if (isset($options['footer'])) {
            $this->mpdf->SetFooter($options['footer']);
        }

        if (isset($options['title'])) {
            $this->mpdf->SetTitle($options['title']);
        }

        if (isset($options['author'])) {
            $this->mpdf->SetAuthor($options['author']);
        }

        $this->mpdf->WriteHTML($html);

        return $this->mpdf->Output('', 'S');
    }

    public function generateFromHtml($html, $options = [])
    {
        // Apply options
        if (isset($options['header'])) {
            $this->mpdf->SetHeader($options['header']);
        }

        if (isset($options['footer'])) {
            $this->mpdf->SetFooter($options['footer']);
        }

        if (isset($options['title'])) {
            $this->mpdf->SetTitle($options['title']);
        }

        $this->mpdf->WriteHTML($html);

        return $this->mpdf->Output('', 'S');
    }

    public function saveToFile($html, $filename, $options = [])
    {
        $this->generateFromHtml($html, $options);

        $path = storage_path('app/public/reports/' . $filename);

        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $this->mpdf->Output($path, 'F');

        return $path;
    }

    public function stream($html, $filename = 'document.pdf', $options = [])
    {
        $this->generateFromHtml($html, $options);

        return $this->mpdf->Output($filename, 'I');
    }

    public function download($html, $filename = 'document.pdf', $options = [])
    {
        $this->generateFromHtml($html, $options);

        return $this->mpdf->Output($filename, 'D');
    }

    public function generateSalesReport($sales, $startDate, $endDate, $summary)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: DejaVuSans, sans-serif; font-size: 10px; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .text-bold { font-weight: bold; }
                .text-large { font-size: 14px; }
                .text-small { font-size: 8px; }
                .border { border: 1px solid #000; }
                .border-top { border-top: 1px solid #000; }
                .border-bottom { border-bottom: 1px solid #000; }
                .mt-1 { margin-top: 4px; }
                .mb-1 { margin-bottom: 4px; }
                .py-1 { padding-top: 4px; padding-bottom: 4px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 4px; border: 1px solid #ddd; }
                .summary-box { background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
            </style>
        </head>
        <body>';

        // Header
        $html .= '
        <div class="text-center mb-1">
            <h1 class="text-large text-bold">Laporan Penjualan</h1>
            <div>Periode: ' . $startDate . ' s/d ' . $endDate . '</div>
            <div>Dibuat: ' . now()->format('d/m/Y H:i:s') . '</div>
        </div>';

        // Summary
        $html .= '
        <div class="summary-box">
            <h3>Ringkasan</h3>
            <table>
                <tr>
                    <td>Total Penjualan</td>
                    <td class="text-right">Rp ' . number_format($summary['total_sales'], 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td>Total Transaksi</td>
                    <td class="text-right">' . number_format($summary['total_transactions'], 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td>Rata-rata Transaksi</td>
                    <td class="text-right">Rp ' . number_format($summary['average_transaction'], 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td>Total Item Terjual</td>
                    <td class="text-right">' . number_format($summary['total_items'], 0, ',', '.') . '</td>
                </tr>
            </table>
        </div>';

        // Sales Table
        $html .= '
        <h3>Detail Transaksi</h3>
        <table>
            <thead>
                <tr class="border-bottom">
                    <th>No. Invoice</th>
                    <th>Tanggal</th>
                    <th>Customer</th>
                    <th>Kasir</th>
                    <th class="text-right">Items</th>
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">Pajak</th>
                    <th class="text-right">Diskon</th>
                    <th class="text-right">Total</th>
                    <th>Metode</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($sales as $sale) {
            $html .= '
            <tr>
                <td>' . $sale->invoice_number . '</td>
                <td>' . $sale->sale_date->format('d/m/Y') . '</td>
                <td>' . ($sale->customer ? $sale->customer->name : 'Umum') . '</td>
                <td>' . $sale->user->name . '</td>
                <td class="text-right">' . $sale->items_count . '</td>
                <td class="text-right">' . number_format($sale->subtotal, 0, ',', '.') . '</td>
                <td class="text-right">' . number_format($sale->tax, 0, ',', '.') . '</td>
                <td class="text-right">' . number_format($sale->discount, 0, ',', '.') . '</td>
                <td class="text-right text-bold">' . number_format($sale->grand_total, 0, ',', '.') . '</td>
                <td>' . ucfirst($sale->payment_method) . '</td>
                <td>' . ucfirst($sale->payment_status) . '</td>
            </tr>';
        }

        $html .= '
            </tbody>
        </table>';

        // Footer
        $html .= '
        <div class="text-center text-small mt-1">
            <div>Laporan dihasilkan oleh Sistem POS</div>
            <div>' . date('d/m/Y H:i:s') . '</div>
        </div>';

        $html .= '
        </body>
        </html>';

        return $html;
    }

    public function generateStockReport($products, $summary)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: DejaVuSans, sans-serif; font-size: 9px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 3px; border: 1px solid #ddd; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .text-bold { font-weight: bold; }
                .summary-box { background: #f8f9fa; padding: 8px; margin-bottom: 15px; }
            </style>
        </head>
        <body>';

        // Header
        $html .= '
        <div class="text-center">
            <h2>Laporan Stok Barang</h2>
            <div>Periode: ' . now()->format('d/m/Y') . '</div>
        </div>';

        // Summary
        $html .= '
        <div class="summary-box">
            <table>
                <tr>
                    <td>Total Produk</td>
                    <td class="text-right">' . number_format($summary['total_products'], 0, ',', '.') . '</td>
                    <td>Total Stok</td>
                    <td class="text-right">' . number_format($summary['total_stock'], 0, ',', '.') . ' unit</td>
                </tr>
                <tr>
                    <td>Nilai Stok</td>
                    <td class="text-right">Rp ' . number_format($summary['stock_value'], 0, ',', '.') . '</td>
                    <td>Stok Menipis</td>
                    <td class="text-right">' . number_format($summary['low_stock_count'], 0, ',', '.') . ' produk</td>
                </tr>
                <tr>
                    <td>Stok Habis</td>
                    <td class="text-right">' . number_format($summary['out_of_stock_count'], 0, ',', '.') . ' produk</td>
                    <td colspan="2"></td>
                </tr>
            </table>
        </div>';

        // Products Table
        $html .= '
        <table>
            <thead>
                <tr class="text-bold">
                    <th>Kode</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Satuan</th>
                    <th class="text-right">Harga Beli</th>
                    <th class="text-right">Harga Jual</th>
                    <th class="text-right">Stok</th>
                    <th class="text-right">Min. Stok</th>
                    <th class="text-right">Nilai Stok</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($products as $product) {
            $stockValue = $product->stock * $product->purchase_price;
            $status = $product->stock <= 0 ? 'HABIS' : ($product->stock <= $product->min_stock ? 'MENIPIS' : 'NORMAL');
            $statusClass = $product->stock <= 0 ? 'text-bold" style="color: red;' : ($product->stock <= $product->min_stock ? 'text-bold" style="color: orange;' : '');

            $html .= '
            <tr>
                <td>' . $product->code . '</td>
                <td>' . $product->name . '</td>
                <td>' . ($product->category ? $product->category->name : '-') . '</td>
                <td>' . ($product->unit ? $product->unit->short_name : '-') . '</td>
                <td class="text-right">' . number_format($product->purchase_price, 0, ',', '.') . '</td>
                <td class="text-right">' . number_format($product->selling_price, 0, ',', '.') . '</td>
                <td class="text-right">' . number_format($product->stock, 0, ',', '.') . '</td>
                <td class="text-right">' . number_format($product->min_stock, 0, ',', '.') . '</td>
                <td class="text-right">' . number_format($stockValue, 0, ',', '.') . '</td>
                <td class="' . $statusClass . '">' . $status . '</td>
            </tr>';
        }

        $html .= '
            </tbody>
        </table>';

        $html .= '
        </body>
        </html>';

        return $html;
    }
}
