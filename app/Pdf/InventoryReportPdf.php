<?php

namespace App\Pdf;

use Mpdf\Mpdf;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class InventoryReportPdf
{
    protected $products;
    protected $title;
    protected $filters;
    protected $mpdf;
    protected $storeInfo;

    public function __construct($products, $title = 'Laporan Inventory', $filters = [])
    {
        $this->products = $products;
        $this->title = $title;
        $this->filters = $filters;
        $this->storeInfo = [
            'name' => config('app.name', 'Toko Saya'),
            'address' => 'Jl. Contoh No. 123, Kota Contoh',
            'phone' => '(021) 12345678',
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
            'default_font' => 'arial',
            'tempDir' => storage_path('app/mpdf/tmp'),
        ];

        $this->mpdf = new Mpdf($config);
        $this->mpdf->SetHeader('|' . $this->title . '|');
        $this->mpdf->SetFooter('{PAGENO}');
    }

    public function generate(): string
    {
        $summary = $this->calculateSummary();

        $this->mpdf->WriteHTML($this->getHeader());
        $this->mpdf->WriteHTML($this->getReportInfo());
        $this->mpdf->WriteHTML($this->getSummarySection($summary));
        $this->mpdf->WriteHTML($this->getProductsTable());

        return $this->mpdf->Output('', 'S');
    }

    protected function calculateSummary()
    {
        $totalProducts = $this->products->count();
        $totalStock = $this->products->sum('stock');
        $totalValue = $this->products->sum(function ($product) {
            return $product->stock * $product->purchase_price;
        });

        $lowStockCount = $this->products->where('stock', '>', 0)
            ->where('stock', '<=', DB::raw('min_stock'))
            ->count();

        $outOfStockCount = $this->products->where('stock', '<=', 0)->count();

        $averageStock = $totalProducts > 0 ? $totalStock / $totalProducts : 0;
        $averageValue = $totalProducts > 0 ? $totalValue / $totalProducts : 0;

        return [
            'total_products' => $totalProducts,
            'total_stock' => $totalStock,
            'total_value' => $totalValue,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'average_stock' => $averageStock,
            'average_value' => $averageValue,
        ];
    }

    protected function getHeader(): string
    {
        return '
        <div style="text-align: center; margin-bottom: 20px;">
            <h1 style="margin: 0; color: #333;">' . $this->storeInfo['name'] . '</h1>
            <p style="margin: 5px 0; color: #666;">' . $this->storeInfo['address'] . ' | ' . $this->storeInfo['phone'] . '</p>
            <h2 style="margin: 10px 0; color: #555;">' . $this->title . '</h2>
        </div>
        ';
    }

    protected function getReportInfo(): string
    {
        $generatedAt = now()->translatedFormat('l, d F Y H:i:s');

        $filterInfo = '';
        if (!empty($this->filters)) {
            $filterInfo = '<p style="margin: 5px 0;"><strong>Filter:</strong> ';
            $filterParts = [];

            if (isset($this->filters['category'])) {
                $filterParts[] = 'Kategori: ' . $this->filters['category'];
            }
            if (isset($this->filters['stock_status'])) {
                $filterParts[] = 'Status Stok: ' . $this->filters['stock_status'];
            }
            if (isset($this->filters['low_stock_only'])) {
                $filterParts[] = 'Hanya Stok Menipis';
            }

            $filterInfo .= implode(', ', $filterParts) . '</p>';
        }

        return '
        <div style="margin-bottom: 20px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 30%;"><strong>Dibuat Pada:</strong></td>
                    <td>' . $generatedAt . '</td>
                </tr>
                ' . (!empty($filterInfo) ? '
                <tr>
                    <td><strong>Filter:</strong></td>
                    <td>' . $filterInfo . '</td>
                </tr>' : '') . '
            </table>
        </div>
        ';
    }

    protected function getSummarySection($summary): string
    {
        $html = '
        <div style="margin-bottom: 20px;">
            <h3 style="color: #555; border-bottom: 2px solid #28a745; padding-bottom: 5px;">Ringkasan Inventory</h3>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                <div style="flex: 1; min-width: 200px; padding: 15px; background: #28a745; color: white; border-radius: 5px;">
                    <h4 style="margin: 0 0 5px 0; font-size: 14px;">Total Produk</h4>
                    <p style="margin: 0; font-size: 24px; font-weight: bold;">' . number_format($summary['total_products'], 0, ',', '.') . '</p>
                </div>
                <div style="flex: 1; min-width: 200px; padding: 15px; background: #17a2b8; color: white; border-radius: 5px;">
                    <h4 style="margin: 0 0 5px 0; font-size: 14px;">Total Stok</h4>
                    <p style="margin: 0; font-size: 24px; font-weight: bold;">' . number_format($summary['total_stock'], 0, ',', '.') . '</p>
                </div>
                <div style="flex: 1; min-width: 200px; padding: 15px; background: #007bff; color: white; border-radius: 5px;">
                    <h4 style="margin: 0 0 5px 0; font-size: 14px;">Nilai Inventory</h4>
                    <p style="margin: 0; font-size: 24px; font-weight: bold;">Rp ' . number_format($summary['total_value'], 0, ',', '.') . '</p>
                </div>
            </div>
            
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <div style="flex: 1; min-width: 200px; padding: 15px; background: #ffc107; color: #333; border-radius: 5px;">
                    <h4 style="margin: 0 0 5px 0; font-size: 14px;">Stok Menipis</h4>
                    <p style="margin: 0; font-size: 24px; font-weight: bold;">' . number_format($summary['low_stock_count'], 0, ',', '.') . '</p>
                </div>
                <div style="flex: 1; min-width: 200px; padding: 15px; background: #dc3545; color: white; border-radius: 5px;">
                    <h4 style="margin: 0 0 5px 0; font-size: 14px;">Stok Habis</h4>
                    <p style="margin: 0; font-size: 24px; font-weight: bold;">' . number_format($summary['out_of_stock_count'], 0, ',', '.') . '</p>
                </div>
                <div style="flex: 1; min-width: 200px; padding: 15px; background: #6c757d; color: white; border-radius: 5px;">
                    <h4 style="margin: 0 0 5px 0; font-size: 14px;">Rata-rata Stok</h4>
                    <p style="margin: 0; font-size: 24px; font-weight: bold;">' . number_format($summary['average_stock'], 1) . '</p>
                </div>
            </div>
        </div>
        ';

        return $html;
    }

    protected function getProductsTable(): string
    {
        $html = '
        <div style="margin-bottom: 20px;">
            <h3 style="color: #555; border-bottom: 2px solid #28a745; padding-bottom: 5px;">Detail Produk</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 10px;">
                <thead>
                    <tr style="background: #343a40; color: white;">
                        <th style="padding: 8px; text-align: left; width: 5%;">No</th>
                        <th style="padding: 8px; text-align: left; width: 15%;">Kode</th>
                        <th style="padding: 8px; text-align: left; width: 25%;">Nama Produk</th>
                        <th style="padding: 8px; text-align: left; width: 15%;">Kategori</th>
                        <th style="padding: 8px; text-align: right; width: 8%;">Stok</th>
                        <th style="padding: 8px; text-align: right; width: 8%;">Min. Stok</th>
                        <th style="padding: 8px; text-align: right; width: 10%;">Harga Beli</th>
                        <th style="padding: 8px; text-align: right; width: 10%;">Nilai Stok</th>
                        <th style="padding: 8px; text-align: center; width: 8%;">Status</th>
                    </tr>
                </thead>
                <tbody>
        ';

        $no = 1;
        $currentCategory = null;

        foreach ($this->products as $product) {
            // Add category header if category changes
            if ($product->category_id !== $currentCategory) {
                $currentCategory = $product->category_id;
                $categoryName = $product->category?->name ?? 'Tanpa Kategori';

                $html .= '
                <tr style="background: #e9ecef;">
                    <td colspan="9" style="padding: 8px; font-weight: bold; border-bottom: 2px solid #dee2e6;">
                        ' . $categoryName . '
                    </td>
                </tr>
                ';
            }

            // Determine stock status and color
            $stockStatus = '';
            $statusColor = '';

            if ($product->stock <= 0) {
                $stockStatus = 'HABIS';
                $statusColor = '#dc3545';
            } elseif ($product->stock <= $product->min_stock) {
                $stockStatus = 'MENIPIS';
                $statusColor = '#ffc107';
            } else {
                $stockStatus = 'NORMAL';
                $statusColor = '#28a745';
            }

            $stockValue = $product->stock * $product->purchase_price;

            $html .= '
            <tr style="border-bottom: 1px solid #dee2e6;">
                <td style="padding: 8px;">' . $no++ . '</td>
                <td style="padding: 8px;">' . $product->code . '</td>
                <td style="padding: 8px;">' . $product->name . '</td>
                <td style="padding: 8px;">' . ($product->category?->name ?? '-') . '</td>
                <td style="padding: 8px; text-align: right;">' . number_format($product->stock, 0, ',', '.') . '</td>
                <td style="padding: 8px; text-align: right;">' . number_format($product->min_stock, 0, ',', '.') . '</td>
                <td style="padding: 8px; text-align: right;">Rp ' . number_format($product->purchase_price, 0, ',', '.') . '</td>
                <td style="padding: 8px; text-align: right;">Rp ' . number_format($stockValue, 0, ',', '.') . '</td>
                <td style="padding: 8px; text-align: center; font-weight: bold; color: ' . $statusColor . ';">
                    ' . $stockStatus . '
                </td>
            </tr>
            ';
        }

        $html .= '
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px; font-size: 10px;">
            <p style="margin: 5px 0;"><strong>Legenda Status:</strong></p>
            <p style="margin: 5px 0;">
                <span style="color: #28a745; font-weight: bold;">NORMAL</span> = Stok di atas minimum
            </p>
            <p style="margin: 5px 0;">
                <span style="color: #ffc107; font-weight: bold;">MENIPIS</span> = Stok di bawah atau sama dengan minimum
            </p>
            <p style="margin: 5px 0;">
                <span style="color: #dc3545; font-weight: bold;">HABIS</span> = Stok 0 atau minus
            </p>
        </div>
        ';

        return $html;
    }

    public function download(string $filename = null): void
    {
        $filename = $filename ?? 'laporan-inventory-' . date('Y-m-d-H-i') . '.pdf';
        $this->mpdf->Output($filename, 'D');
    }

    public function stream(): void
    {
        $this->mpdf->Output('laporan-inventory.pdf', 'I');
    }
}
