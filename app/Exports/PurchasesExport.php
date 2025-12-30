<?php

namespace App\Exports;

use App\Models\Purchase;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchasesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;
    protected $supplierId;
    protected $paymentStatus;

    public function __construct($startDate = null, $endDate = null, $supplierId = null, $paymentStatus = null)
    {
        $this->startDate = $startDate ?? now()->startOfMonth();
        $this->endDate = $endDate ?? now()->endOfMonth();
        $this->supplierId = $supplierId;
        $this->paymentStatus = $paymentStatus;
    }

    public function collection()
    {
        $query = Purchase::with(['supplier', 'user', 'items.product'])
            ->whereBetween('purchase_date', [$this->startDate, $this->endDate])
            ->orderBy('purchase_date', 'desc');

        if ($this->supplierId) {
            $query->where('supplier_id', $this->supplierId);
        }

        if ($this->paymentStatus) {
            $query->where('payment_status', $this->paymentStatus);
        }

        return $query->get();
    }

    public function map($purchase): array
    {
        $paymentStatusLabel = match ($purchase->payment_status) {
            'paid' => 'Lunas',
            'partial' => 'Bayar Sebagian',
            'unpaid' => 'Belum Bayar',
            default => $purchase->payment_status,
        };

        $paymentMethodLabel = match ($purchase->payment_method) {
            'cash' => 'Tunai',
            'transfer' => 'Transfer',
            'credit' => 'Kredit',
            default => $purchase->payment_method,
        };

        $itemsCount = $purchase->items->count();
        $totalItems = $purchase->items->sum('quantity');

        return [
            $purchase->invoice_number,
            $purchase->purchase_date->format('d/m/Y'),
            $purchase->supplier?->name ?? '-',
            $purchase->supplier?->code ?? '-',
            $purchase->user->name,
            $itemsCount,
            $totalItems,
            number_format($purchase->subtotal, 0, ',', '.'),
            number_format($purchase->tax, 0, ',', '.'),
            number_format($purchase->discount, 0, ',', '.'),
            number_format($purchase->shipping_cost, 0, ',', '.'),
            number_format($purchase->grand_total, 0, ',', '.'),
            $paymentMethodLabel,
            $paymentStatusLabel,
            number_format($purchase->paid_amount, 0, ',', '.'),
            number_format($purchase->due_amount, 0, ',', '.'),
            $purchase->due_date ? $purchase->due_date->format('d/m/Y') : '-',
            $purchase->notes ?? '-',
            $purchase->created_at->format('d/m/Y H:i'),
        ];
    }

    public function headings(): array
    {
        return [
            'NO. INVOICE',
            'TANGGAL',
            'SUPPLIER',
            'KODE SUPPLIER',
            'DIBUAT OLEH',
            'JUMLAH ITEM',
            'TOTAL QTY',
            'SUBTOTAL',
            'PAJAK',
            'DISKON',
            'BIAYA PENGIRIMAN',
            'TOTAL',
            'METODE BAYAR',
            'STATUS BAYAR',
            'DIBAYAR',
            'SISA HUTANG',
            'TANGGAL JATUH TEMPO',
            'CATATAN',
            'DIBUAT PADA',
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
            'F' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'G' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'H' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'I' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'J' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'K' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'L' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'O' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'P' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],

            // Payment status colors
            'N' => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ],

            // Auto size all columns
            'A:S' => [
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
            'A' => 18, // No. Invoice
            'B' => 12, // Tanggal
            'C' => 25, // Supplier
            'D' => 15, // Kode Supplier
            'E' => 20, // Dibuat Oleh
            'F' => 12, // Jumlah Item
            'G' => 12, // Total Qty
            'H' => 15, // Subtotal
            'I' => 12, // Pajak
            'J' => 12, // Diskon
            'K' => 18, // Biaya Pengiriman
            'L' => 15, // Total
            'M' => 15, // Metode Bayar
            'N' => 15, // Status Bayar
            'O' => 15, // Dibayar
            'P' => 15, // Sisa Hutang
            'Q' => 18, // Tanggal Jatuh Tempo
            'R' => 25, // Catatan
            'S' => 18, // Dibuat Pada
        ];
    }

    public function title(): string
    {
        $title = 'Data Pembelian';

        if ($this->supplierId) {
            $supplier = \App\Models\Supplier::find($this->supplierId);
            if ($supplier) {
                $title .= ' - ' . $supplier->name;
            }
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
                $sheet->setAutoFilter('A1:S1');

                // Freeze first row
                $sheet->freezePane('A2');

                // Add summary row
                $totalRows = $sheet->getHighestRow();
                $summaryRow = $totalRows + 2;

                // Calculate totals
                $totalPurchases = $totalRows - 1;
                $totalAmount = 0;
                $totalPaid = 0;
                $totalDue = 0;
                $totalItems = 0;
                $totalQuantity = 0;

                $paidCount = 0;
                $partialCount = 0;
                $unpaidCount = 0;

                for ($row = 2; $row <= $totalRows; $row++) {
                    $amount = $sheet->getCell('L' . $row)->getValue();
                    $paid = $sheet->getCell('O' . $row)->getValue();
                    $due = $sheet->getCell('P' . $row)->getValue();
                    $items = $sheet->getCell('F' . $row)->getValue();
                    $quantity = $sheet->getCell('G' . $row)->getValue();
                    $status = $sheet->getCell('N' . $row)->getValue();

                    $totalAmount += (float)str_replace(['.', ','], '', $amount);
                    $totalPaid += (float)str_replace(['.', ','], '', $paid);
                    $totalDue += (float)str_replace(['.', ','], '', $due);
                    $totalItems += (int)$items;
                    $totalQuantity += (int)$quantity;

                    switch ($status) {
                        case 'Lunas':
                            $paidCount++;
                            break;
                        case 'Bayar Sebagian':
                            $partialCount++;
                            break;
                        case 'Belum Bayar':
                            $unpaidCount++;
                            break;
                    }
                }

                // Add summary
                $sheet->setCellValue('A' . $summaryRow, 'RINGKASAN');
                $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true);

                $sheet->setCellValue('A' . ($summaryRow + 1), 'Total Pembelian:');
                $sheet->setCellValue('B' . ($summaryRow + 1), $totalPurchases);

                $sheet->setCellValue('A' . ($summaryRow + 2), 'Total Item:');
                $sheet->setCellValue('B' . ($summaryRow + 2), $totalItems);

                $sheet->setCellValue('A' . ($summaryRow + 3), 'Total Quantity:');
                $sheet->setCellValue('B' . ($summaryRow + 3), $totalQuantity);

                $sheet->setCellValue('A' . ($summaryRow + 4), 'Total Nilai:');
                $sheet->setCellValue('B' . ($summaryRow + 4), 'Rp ' . number_format($totalAmount, 0, ',', '.'));

                $sheet->setCellValue('A' . ($summaryRow + 5), 'Total Dibayar:');
                $sheet->setCellValue('B' . ($summaryRow + 5), 'Rp ' . number_format($totalPaid, 0, ',', '.'));

                $sheet->setCellValue('A' . ($summaryRow + 6), 'Total Hutang:');
                $sheet->setCellValue('B' . ($summaryRow + 6), 'Rp ' . number_format($totalDue, 0, ',', '.'));

                $sheet->setCellValue('A' . ($summaryRow + 7), 'Status Pembayaran:');
                $sheet->setCellValue('B' . ($summaryRow + 7), "Lunas: {$paidCount}, Sebagian: {$partialCount}, Belum: {$unpaidCount}");

                // Style summary
                $summaryRange = 'A' . $summaryRow . ':B' . ($summaryRow + 7);
                $sheet->getStyle($summaryRange)->getFont()->setBold(true);
                $sheet->getStyle($summaryRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $sheet->getStyle($summaryRange)->getFill()->getStartColor()->setARGB('FFF2F2F2');

                // Color code payment status
                for ($row = 2; $row <= $totalRows; $row++) {
                    $status = $sheet->getCell('N' . $row)->getValue();

                    $color = match ($status) {
                        'Lunas' => 'FF28A745',
                        'Bayar Sebagian' => 'FFFFC107',
                        'Belum Bayar' => 'FFDC3545',
                        default => 'FFFFFFFF',
                    };

                    $sheet->getStyle('N' . $row)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($color);

                    // Also color the status text
                    $textColor = match ($status) {
                        'Lunas' => 'FFFFFFFF',
                        'Bayar Sebagian' => 'FF000000',
                        'Belum Bayar' => 'FFFFFFFF',
                        default => 'FF000000',
                    };

                    $sheet->getStyle('N' . $row)->getFont()->getColor()->setARGB($textColor);
                }

                // Add period info
                $periodRow = $summaryRow + 9;
                $sheet->setCellValue('A' . $periodRow, 'Periode: ' . \Carbon\Carbon::parse($this->startDate)->format('d/m/Y') . ' - ' . \Carbon\Carbon::parse($this->endDate)->format('d/m/Y'));
                $sheet->mergeCells('A' . $periodRow . ':C' . $periodRow);

                // Add generated at info
                $generatedRow = $periodRow + 1;
                $sheet->setCellValue('A' . $generatedRow, 'Dibuat pada: ' . now()->format('d/m/Y H:i:s'));
                $sheet->mergeCells('A' . $generatedRow . ':C' . $generatedRow);
                $sheet->getStyle('A' . $generatedRow)->getFont()->setItalic(true);
            },
        ];
    }
}
