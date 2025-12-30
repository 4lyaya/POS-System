<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockReportExport implements FromCollection, WithMapping, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $categoryId;
    protected $lowStockOnly;

    public function __construct($categoryId = null, $lowStockOnly = false)
    {
        $this->categoryId = $categoryId;
        $this->lowStockOnly = $lowStockOnly;
    }

    public function collection()
    {
        $query = Product::with(['category', 'unit']);

        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

        if ($this->lowStockOnly) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        return $query->orderBy('category_id')
            ->orderBy('name')
            ->get();
    }

    public function map($product): array
    {
        $stockStatus = 'Normal';
        if ($product->stock <= 0) {
            $stockStatus = 'Habis';
        } elseif ($product->stock <= $product->min_stock) {
            $stockStatus = 'Menipis';
        }

        $stockValue = $product->stock * $product->purchase_price;

        return [
            $product->code,
            $product->barcode,
            $product->name,
            $product->category?->name ?? '-',
            $product->unit?->short_name ?? '-',
            number_format($product->purchase_price, 0, ',', '.'),
            number_format($product->selling_price, 0, ',', '.'),
            $product->stock,
            $product->min_stock,
            $stockStatus,
            number_format($stockValue, 0, ',', '.'),
            $product->is_active ? 'Aktif' : 'Nonaktif',
        ];
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Barcode',
            'Nama Produk',
            'Kategori',
            'Satuan',
            'Harga Beli',
            'Harga Jual',
            'Stok',
            'Min. Stok',
            'Status',
            'Nilai Stok',
            'Status Aktif',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],

            'A:L' => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                ],
            ],

            // Format number columns
            'F' => ['numberFormat' => ['formatCode' => '#,##0']],
            'G' => ['numberFormat' => ['formatCode' => '#,##0']],
            'K' => ['numberFormat' => ['formatCode' => '#,##0']],

            // Color code stock status
            'J' => [
                'font' => [
                    'color' => ['rgb' => 'FF0000']
                ]
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Code
            'B' => 20, // Barcode
            'C' => 30, // Name
            'D' => 20, // Category
            'E' => 10, // Unit
            'F' => 15, // Purchase Price
            'G' => 15, // Selling Price
            'H' => 10, // Stock
            'I' => 10, // Min Stock
            'J' => 12, // Status
            'K' => 15, // Stock Value
            'L' => 12, // Active Status
        ];
    }

    public function title(): string
    {
        $title = 'Laporan Stok';
        if ($this->lowStockOnly) {
            $title .= ' (Stok Menipis)';
        }
        return $title;
    }
}
