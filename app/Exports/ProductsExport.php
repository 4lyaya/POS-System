<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, ShouldAutoSize
{
    protected $categoryId;
    protected $stockStatus;
    protected $lowStockOnly;

    public function __construct($categoryId = null, $stockStatus = null, $lowStockOnly = false)
    {
        $this->categoryId = $categoryId;
        $this->stockStatus = $stockStatus;
        $this->lowStockOnly = $lowStockOnly;
    }

    public function collection()
    {
        $query = Product::with(['category', 'unit']);

        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

        if ($this->stockStatus) {
            switch ($this->stockStatus) {
                case 'low':
                    $query->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0);
                    break;
                case 'out':
                    $query->where('stock', '<=', 0);
                    break;
                case 'normal':
                    $query->whereColumn('stock', '>', 'min_stock');
                    break;
            }
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
        $profitMargin = $product->purchase_price > 0
            ? (($product->selling_price - $product->purchase_price) / $product->purchase_price) * 100
            : 0;

        return [
            $product->code,
            $product->barcode ?? '-',
            $product->name,
            $product->category?->name ?? '-',
            $product->unit?->name ?? '-',
            $product->unit?->short_name ?? '-',
            number_format($product->purchase_price, 0, ',', '.'),
            number_format($product->selling_price, 0, ',', '.'),
            number_format($product->wholesale_price ?? 0, 0, ',', '.'),
            $product->stock,
            $product->min_stock,
            $product->max_stock ?? '-',
            number_format($stockValue, 0, ',', '.'),
            number_format($profitMargin, 2) . '%',
            $stockStatus,
            $product->weight ? number_format($product->weight, 2) . ' kg' : '-',
            $product->dimension ?? '-',
            $product->expired_date ? $product->expired_date->format('d/m/Y') : '-',
            $product->description ?? '-',
            $product->is_active ? 'Aktif' : 'Nonaktif',
            $product->created_at->format('d/m/Y H:i'),
            $product->updated_at->format('d/m/Y H:i'),
        ];
    }

    public function headings(): array
    {
        return [
            'KODE',
            'BARCODE',
            'NAMA PRODUK',
            'KATEGORI',
            'SATUAN',
            'SINGKATAN',
            'HARGA BELI',
            'HARGA JUAL',
            'HARGA GROSIR',
            'STOK',
            'STOK MINIMUM',
            'STOK MAKSIMUM',
            'NILAI STOK',
            'MARGIN KEUNTUNGAN',
            'STATUS STOK',
            'BERAT',
            'DIMENSI',
            'TANGGAL KADALUARSA',
            'DESKRIPSI',
            'STATUS AKTIF',
            'DIBUAT PADA',
            'DIPERBARUI PADA',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header style
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2C3E50']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ]
            ],

            // Number columns
            'G' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'H' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'I' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'J' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'K' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'L' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'M' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'N' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],

            // Status column color coding
            'O' => [
                'font' => [
                    'bold' => true
                ]
            ],

            // Auto size all columns
            'A:V' => [
                'alignment' => [
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                    'wrapText' => true
                ]
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Kode
            'B' => 20, // Barcode
            'C' => 30, // Nama Produk
            'D' => 20, // Kategori
            'E' => 15, // Satuan
            'F' => 12, // Singkatan
            'G' => 15, // Harga Beli
            'H' => 15, // Harga Jual
            'I' => 15, // Harga Grosir
            'J' => 10, // Stok
            'K' => 12, // Stok Minimum
            'L' => 12, // Stok Maksimum
            'M' => 15, // Nilai Stok
            'N' => 15, // Margin
            'O' => 12, // Status Stok
            'P' => 12, // Berat
            'Q' => 15, // Dimensi
            'R' => 15, // Tanggal Kadaluarsa
            'S' => 30, // Deskripsi
            'T' => 12, // Status Aktif
            'U' => 18, // Dibuat Pada
            'V' => 18, // Diperbarui Pada
        ];
    }

    public function title(): string
    {
        $title = 'Data Produk';

        if ($this->categoryId) {
            $category = \App\Models\Category::find($this->categoryId);
            if ($category) {
                $title .= ' - ' . $category->name;
            }
        }

        if ($this->lowStockOnly) {
            $title .= ' (Stok Menipis)';
        }

        return $title;
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Set auto filter
                $sheet->setAutoFilter('A1:V1');

                // Freeze first row
                $sheet->freezePane('A2');

                // Add summary row
                $totalRows = $sheet->getHighestRow();
                $summaryRow = $totalRows + 2;

                // Calculate totals
                $totalStock = 0;
                $totalValue = 0;
                $lowStockCount = 0;
                $outOfStockCount = 0;

                for ($row = 2; $row <= $totalRows; $row++) {
                    $stock = $sheet->getCell('J' . $row)->getValue();
                    $value = $sheet->getCell('M' . $row)->getValue();
                    $status = $sheet->getCell('O' . $row)->getValue();

                    $totalStock += (int)$stock;
                    $totalValue += (float)str_replace(['.', ','], '', $value);

                    if ($status === 'Menipis') {
                        $lowStockCount++;
                    } elseif ($status === 'Habis') {
                        $outOfStockCount++;
                    }
                }

                // Add summary
                $sheet->setCellValue('A' . $summaryRow, 'RINGKASAN');
                $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true);

                $sheet->setCellValue('A' . ($summaryRow + 1), 'Total Produk:');
                $sheet->setCellValue('B' . ($summaryRow + 1), $totalRows - 1);

                $sheet->setCellValue('A' . ($summaryRow + 2), 'Total Stok:');
                $sheet->setCellValue('B' . ($summaryRow + 2), $totalStock);

                $sheet->setCellValue('A' . ($summaryRow + 3), 'Total Nilai Stok:');
                $sheet->setCellValue('B' . ($summaryRow + 3), 'Rp ' . number_format($totalValue, 0, ',', '.'));

                $sheet->setCellValue('A' . ($summaryRow + 4), 'Stok Menipis:');
                $sheet->setCellValue('B' . ($summaryRow + 4), $lowStockCount);

                $sheet->setCellValue('A' . ($summaryRow + 5), 'Stok Habis:');
                $sheet->setCellValue('B' . ($summaryRow + 5), $outOfStockCount);

                // Style summary
                $summaryRange = 'A' . $summaryRow . ':B' . ($summaryRow + 5);
                $sheet->getStyle($summaryRange)->getFont()->setBold(true);
                $sheet->getStyle($summaryRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $sheet->getStyle($summaryRange)->getFill()->getStartColor()->setARGB('FFF2F2F2');

                // Add generated at info
                $generatedRow = $summaryRow + 7;
                $sheet->setCellValue('A' . $generatedRow, 'Dibuat pada: ' . now()->format('d/m/Y H:i:s'));
                $sheet->mergeCells('A' . $generatedRow . ':C' . $generatedRow);
                $sheet->getStyle('A' . $generatedRow)->getFont()->setItalic(true);
            },
        ];
    }
}
