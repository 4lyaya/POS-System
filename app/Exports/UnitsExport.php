<?php

namespace App\Exports;

use App\Models\Unit;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UnitsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, ShouldAutoSize
{
    public function collection()
    {
        return Unit::orderBy('name')->get();
    }

    public function map($unit): array
    {
        $productCount = $unit->products()->count();

        return [
            $unit->name,
            $unit->short_name,
            $productCount,
            $unit->is_active ? 'Aktif' : 'Nonaktif',
            $unit->created_at->format('d/m/Y H:i'),
            $unit->updated_at->format('d/m/Y H:i'),
        ];
    }

    public function headings(): array
    {
        return [
            'NAMA SATUAN',
            'SINGKATAN',
            'JUMLAH PRODUK',
            'STATUS',
            'DIBUAT PADA',
            'DIPERBARUI PADA',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
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

            'C' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],

            'A:F' => [
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
            'A' => 25, // Nama Satuan
            'B' => 15, // Singkatan
            'C' => 15, // Jumlah Produk
            'D' => 12, // Status
            'E' => 18, // Dibuat Pada
            'F' => 18, // Diperbarui Pada
        ];
    }

    public function title(): string
    {
        return 'Data Satuan';
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Set auto filter
                $sheet->setAutoFilter('A1:F1');

                // Freeze first row
                $sheet->freezePane('A2');

                // Add summary
                $totalRows = $sheet->getHighestRow();
                $summaryRow = $totalRows + 2;

                $totalUnits = $totalRows - 1;
                $activeUnits = 0;
                $totalProducts = 0;

                for ($row = 2; $row <= $totalRows; $row++) {
                    $status = $sheet->getCell('D' . $row)->getValue();
                    $products = $sheet->getCell('C' . $row)->getValue();

                    if ($status === 'Aktif') {
                        $activeUnits++;
                    }
                    $totalProducts += (int)$products;
                }

                $sheet->setCellValue('A' . $summaryRow, 'RINGKASAN');
                $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true);

                $sheet->setCellValue('A' . ($summaryRow + 1), 'Total Satuan:');
                $sheet->setCellValue('B' . ($summaryRow + 1), $totalUnits);

                $sheet->setCellValue('A' . ($summaryRow + 2), 'Satuan Aktif:');
                $sheet->setCellValue('B' . ($summaryRow + 2), $activeUnits);

                $sheet->setCellValue('A' . ($summaryRow + 3), 'Total Produk Terkait:');
                $sheet->setCellValue('B' . ($summaryRow + 3), $totalProducts);

                $sheet->setCellValue('A' . ($summaryRow + 4), 'Rata-rata Produk per Satuan:');
                $sheet->setCellValue('B' . ($summaryRow + 4), $totalUnits > 0 ? number_format($totalProducts / $totalUnits, 1) : 0);

                // Style summary
                $summaryRange = 'A' . $summaryRow . ':B' . ($summaryRow + 4);
                $sheet->getStyle($summaryRange)->getFont()->setBold(true);
                $sheet->getStyle($summaryRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $sheet->getStyle($summaryRange)->getFill()->getStartColor()->setARGB('FFF2F2F2');

                // Add generated at info
                $generatedRow = $summaryRow + 6;
                $sheet->setCellValue('A' . $generatedRow, 'Dibuat pada: ' . now()->format('d/m/Y H:i:s'));
                $sheet->mergeCells('A' . $generatedRow . ':C' . $generatedRow);
                $sheet->getStyle('A' . $generatedRow)->getFont()->setItalic(true);
            },
        ];
    }
}
