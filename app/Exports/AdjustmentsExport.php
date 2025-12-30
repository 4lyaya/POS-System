<?php

namespace App\Exports;

use App\Models\Adjustment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AdjustmentsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;
    protected $adjustmentType;

    public function __construct($startDate = null, $endDate = null, $adjustmentType = null)
    {
        $this->startDate = $startDate ?? now()->startOfMonth();
        $this->endDate = $endDate ?? now()->endOfMonth();
        $this->adjustmentType = $adjustmentType;
    }

    public function collection()
    {
        $query = Adjustment::with(['user', 'items.product'])
            ->whereBetween('adjustment_date', [$this->startDate, $this->endDate])
            ->orderBy('adjustment_date', 'desc');

        if ($this->adjustmentType) {
            $query->where('adjustment_type', $this->adjustmentType);
        }

        return $query->get();
    }

    public function map($adjustment): array
    {
        $adjustmentTypeLabel = match ($adjustment->adjustment_type) {
            'addition' => 'Penambahan',
            'subtraction' => 'Pengurangan',
            'correction' => 'Koreksi',
            default => $adjustment->adjustment_type,
        };

        $itemsCount = $adjustment->items->count();
        $totalQuantity = $adjustment->items->sum('quantity');

        return [
            $adjustment->adjustment_number,
            $adjustment->adjustment_date->format('d/m/Y'),
            $adjustmentTypeLabel,
            $adjustment->user->name,
            $itemsCount,
            $totalQuantity,
            $adjustment->reason,
            $adjustment->created_at->format('d/m/Y H:i'),
        ];
    }

    public function headings(): array
    {
        return [
            'NO. PENYESUAIAN',
            'TANGGAL',
            'JENIS',
            'DIBUAT OLEH',
            'JUMLAH ITEM',
            'TOTAL QTY',
            'ALASAN',
            'DIBUAT PADA',
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

            'E' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'F' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],

            'A:H' => [
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
            'A' => 18, // No. Penyesuaian
            'B' => 12, // Tanggal
            'C' => 15, // Jenis
            'D' => 20, // Dibuat Oleh
            'E' => 12, // Jumlah Item
            'F' => 12, // Total Qty
            'G' => 30, // Alasan
            'H' => 18, // Dibuat Pada
        ];
    }

    public function title(): string
    {
        $title = 'Data Penyesuaian Stok';

        if ($this->adjustmentType) {
            $typeLabel = match ($this->adjustmentType) {
                'addition' => 'Penambahan',
                'subtraction' => 'Pengurangan',
                'correction' => 'Koreksi',
                default => $this->adjustmentType,
            };
            $title .= ' - ' . $typeLabel;
        }

        $title .= ' (' . \Carbon\Carbon::parse($this->startDate)->format('d/m/Y') . ' - ' . \Carbon\Carbon::parse($this->endDate)->format('d/m/Y') . ')';

        return $title;
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Set auto filter
                $sheet->setAutoFilter('A1:H1');

                // Freeze first row
                $sheet->freezePane('A2');

                // Color code adjustment types
                $totalRows = $sheet->getHighestRow();

                for ($row = 2; $row <= $totalRows; $row++) {
                    $type = $sheet->getCell('C' . $row)->getValue();

                    $color = match ($type) {
                        'Penambahan' => 'FF28A745',
                        'Pengurangan' => 'FFDC3545',
                        'Koreksi' => 'FFFFC107',
                        default => 'FFFFFFFF',
                    };

                    $sheet->getStyle('C' . $row)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($color);
                }
            },
        ];
    }
}
