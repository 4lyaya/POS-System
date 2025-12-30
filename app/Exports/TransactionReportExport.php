<?php

namespace App\Exports;

use Carbon\Carbon;
use App\Models\Sale;
use App\Models\Purchase;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionReportExport implements FromCollection, WithMapping, WithHeadings, WithStyles, WithColumnWidths, WithTitle, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;
    protected $transactionType;

    public function __construct($startDate, $endDate, $transactionType = 'both')
    {
        $this->startDate = Carbon::parse($startDate);
        $this->endDate = Carbon::parse($endDate);
        $this->transactionType = $transactionType;
    }

    public function collection()
    {
        $transactions = collect();

        // Get sales if needed
        if (in_array($this->transactionType, ['sales', 'both'])) {
            $sales = Sale::with(['customer', 'user'])
                ->whereBetween('sale_date', [$this->startDate, $this->endDate])
                ->get()
                ->map(function ($sale) {
                    return [
                        'type' => 'Penjualan',
                        'date' => $sale->sale_date,
                        'transaction_id' => $sale->invoice_number,
                        'customer_supplier' => $sale->customer?->name ?? 'Umum',
                        'user' => $sale->user->name,
                        'total_items' => $sale->items_count,
                        'amount' => $sale->grand_total,
                        'payment_method' => $sale->payment_method,
                        'payment_status' => $sale->payment_status,
                        'notes' => $sale->notes,
                        'profit' => $sale->profit ?? 0,
                    ];
                });

            $transactions = $transactions->merge($sales);
        }

        // Get purchases if needed
        if (in_array($this->transactionType, ['purchases', 'both'])) {
            $purchases = Purchase::with(['supplier', 'user'])
                ->whereBetween('purchase_date', [$this->startDate, $this->endDate])
                ->get()
                ->map(function ($purchase) {
                    return [
                        'type' => 'Pembelian',
                        'date' => $purchase->purchase_date,
                        'transaction_id' => $purchase->invoice_number,
                        'customer_supplier' => $purchase->supplier?->name ?? 'Tanpa Supplier',
                        'user' => $purchase->user->name,
                        'total_items' => $purchase->items->sum('quantity'),
                        'amount' => $purchase->grand_total,
                        'payment_method' => $purchase->payment_method,
                        'payment_status' => $purchase->payment_status,
                        'notes' => $purchase->notes,
                        'profit' => -$purchase->grand_total, // Negative for purchases
                    ];
                });

            $transactions = $transactions->merge($purchases);
        }

        // Sort by date
        return $transactions->sortBy('date');
    }

    public function map($transaction): array
    {
        $paymentMethod = match ($transaction['payment_method']) {
            'cash' => 'Tunai',
            'transfer' => 'Transfer',
            'qris' => 'QRIS',
            'debit' => 'Debit',
            'credit' => 'Kredit',
            default => $transaction['payment_method'],
        };

        $paymentStatus = match ($transaction['payment_status']) {
            'paid' => 'Lunas',
            'partial' => 'Bayar Sebagian',
            'unpaid' => 'Belum Bayar',
            'cancelled' => 'Dibatalkan',
            default => $transaction['payment_status'],
        };

        return [
            $transaction['type'],
            $transaction['date']->format('d/m/Y'),
            $transaction['transaction_id'],
            $transaction['customer_supplier'],
            $transaction['user'],
            $transaction['total_items'],
            number_format($transaction['amount'], 0, ',', '.'),
            $paymentMethod,
            $paymentStatus,
            $transaction['notes'] ?? '-',
            number_format($transaction['profit'], 0, ',', '.'),
        ];
    }

    public function headings(): array
    {
        return [
            'Jenis',
            'Tanggal',
            'No. Transaksi',
            'Customer/Supplier',
            'User',
            'Jumlah Item',
            'Total (Rp)',
            'Metode Bayar',
            'Status',
            'Catatan',
            'Profit/Loss (Rp)',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Define styles
        $styles = [
            // Header row
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '2C3E50']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ]
            ],

            // Center align for certain columns
            'A' => ['alignment' => ['horizontal' => 'center']],
            'B' => ['alignment' => ['horizontal' => 'center']],
            'F' => ['alignment' => ['horizontal' => 'center']],
            'H' => ['alignment' => ['horizontal' => 'center']],
            'I' => ['alignment' => ['horizontal' => 'center']],

            // Right align for amount columns
            'G' => [
                'alignment' => ['horizontal' => 'right'],
                'numberFormat' => ['formatCode' => '#,##0']
            ],
            'K' => [
                'alignment' => ['horizontal' => 'right'],
                'numberFormat' => ['formatCode' => '#,##0']
            ],

            // Auto-size all columns
            'A:K' => [
                'alignment' => ['vertical' => 'center']
            ],
        ];

        // Add conditional formatting for transaction types
        $highestRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $type = $sheet->getCell('A' . $row)->getValue();

            if ($type === 'Penjualan') {
                $sheet->getStyle('A' . $row . ':K' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'E8F5E9'] // Light green
                    ]
                ]);
            } elseif ($type === 'Pembelian') {
                $sheet->getStyle('A' . $row . ':K' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'FFEBEE'] // Light red
                    ]
                ]);
            }

            // Color profit column based on value
            $profit = $sheet->getCell('K' . $row)->getValue();
            $profitCellStyle = $sheet->getStyle('K' . $row);

            if ($profit > 0) {
                $profitCellStyle->applyFromArray([
                    'font' => ['color' => ['rgb' => '28a745']] // Green
                ]);
            } elseif ($profit < 0) {
                $profitCellStyle->applyFromArray([
                    'font' => ['color' => ['rgb' => 'dc3545']] // Red
                ]);
            }
        }

        return $styles;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // Jenis
            'B' => 12,  // Tanggal
            'C' => 20,  // No. Transaksi
            'D' => 25,  // Customer/Supplier
            'E' => 20,  // User
            'F' => 12,  // Jumlah Item
            'G' => 15,  // Total
            'H' => 15,  // Metode Bayar
            'I' => 15,  // Status
            'J' => 25,  // Catatan
            'K' => 15,  // Profit/Loss
        ];
    }

    public function title(): string
    {
        $title = 'Laporan Transaksi';

        if ($this->transactionType === 'sales') {
            $title = 'Laporan Penjualan';
        } elseif ($this->transactionType === 'purchases') {
            $title = 'Laporan Pembelian';
        }

        $title .= ' ' . $this->startDate->format('d/m/Y') . ' - ' . $this->endDate->format('d/m/Y');

        return $title;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Add summary section
                $highestRow = $event->sheet->getHighestRow();

                // Calculate totals
                $totalSales = 0;
                $totalPurchases = 0;
                $totalProfit = 0;

                for ($row = 2; $row <= $highestRow; $row++) {
                    $type = $event->sheet->getCell('A' . $row)->getValue();
                    $amount = $event->sheet->getCell('G' . $row)->getValue();
                    $profit = $event->sheet->getCell('K' . $row)->getValue();

                    if ($type === 'Penjualan') {
                        $totalSales += $amount;
                    } elseif ($type === 'Pembelian') {
                        $totalPurchases += $amount;
                    }

                    $totalProfit += $profit;
                }

                // Add summary rows
                $summaryRow = $highestRow + 2;

                $event->sheet->setCellValue('A' . $summaryRow, 'SUMMARY');
                $event->sheet->getStyle('A' . $summaryRow . ':K' . $summaryRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => '343a40']
                    ],
                    'font' => ['color' => ['rgb' => 'FFFFFF']]
                ]);

                $event->sheet->mergeCells('A' . $summaryRow . ':F' . $summaryRow);

                $summaryRow++;

                // Total Sales
                $event->sheet->setCellValue('A' . $summaryRow, 'Total Penjualan:');
                $event->sheet->setCellValue('G' . $summaryRow, $totalSales);
                $event->sheet->getStyle('G' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0');

                $summaryRow++;

                // Total Purchases
                $event->sheet->setCellValue('A' . $summaryRow, 'Total Pembelian:');
                $event->sheet->setCellValue('G' . $summaryRow, $totalPurchases);
                $event->sheet->getStyle('G' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0');

                $summaryRow++;

                // Net Profit
                $event->sheet->setCellValue('A' . $summaryRow, 'Laba/Rugi Bersih:');
                $event->sheet->setCellValue('K' . $summaryRow, $totalProfit);
                $event->sheet->getStyle('K' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0');

                // Color profit cell
                $profitStyle = $event->sheet->getStyle('K' . $summaryRow);
                if ($totalProfit > 0) {
                    $profitStyle->applyFromArray([
                        'font' => ['color' => ['rgb' => '28a745'], 'bold' => true]
                    ]);
                } elseif ($totalProfit < 0) {
                    $profitStyle->applyFromArray([
                        'font' => ['color' => ['rgb' => 'dc3545'], 'bold' => true]
                    ]);
                }
            },
        ];
    }
}
