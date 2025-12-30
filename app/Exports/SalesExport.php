<?php

namespace App\Exports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesExport implements FromQuery, WithMapping, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $startDate;
    protected $endDate;
    protected $userId;
    protected $paymentMethod;

    public function __construct($startDate = null, $endDate = null, $userId = null, $paymentMethod = null)
    {
        $this->startDate = $startDate ?? now()->startOfMonth();
        $this->endDate = $endDate ?? now()->endOfMonth();
        $this->userId = $userId;
        $this->paymentMethod = $paymentMethod;
    }

    public function query()
    {
        $query = Sale::with(['customer', 'user'])
            ->whereBetween('sale_date', [$this->startDate, $this->endDate])
            ->orderBy('sale_date', 'desc');

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        if ($this->paymentMethod) {
            $query->where('payment_method', $this->paymentMethod);
        }

        return $query;
    }

    public function map($sale): array
    {
        return [
            $sale->invoice_number,
            $sale->sale_date->format('d/m/Y'),
            $sale->customer?->name ?? 'Umum',
            $sale->user->name,
            $sale->items_count,
            number_format($sale->subtotal, 0, ',', '.'),
            number_format($sale->tax, 0, ',', '.'),
            number_format($sale->discount, 0, ',', '.'),
            number_format($sale->service_charge, 0, ',', '.'),
            number_format($sale->grand_total, 0, ',', '.'),
            $sale->payment_method,
            $sale->payment_status,
            number_format($sale->paid_amount, 0, ',', '.'),
            number_format($sale->change_amount, 0, ',', '.'),
            $sale->notes ?? '-',
        ];
    }

    public function headings(): array
    {
        return [
            'No. Invoice',
            'Tanggal',
            'Customer',
            'Kasir',
            'Jumlah Item',
            'Subtotal',
            'Pajak',
            'Diskon',
            'Biaya Layanan',
            'Grand Total',
            'Metode Bayar',
            'Status',
            'Dibayar',
            'Kembalian',
            'Catatan',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],

            // Set alignment for all cells
            'A:O' => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                ],
            ],

            // Format currency columns
            'F' => ['numberFormat' => ['formatCode' => '#,##0']],
            'G' => ['numberFormat' => ['formatCode' => '#,##0']],
            'H' => ['numberFormat' => ['formatCode' => '#,##0']],
            'I' => ['numberFormat' => ['formatCode' => '#,##0']],
            'J' => ['numberFormat' => ['formatCode' => '#,##0']],
            'M' => ['numberFormat' => ['formatCode' => '#,##0']],
            'N' => ['numberFormat' => ['formatCode' => '#,##0']],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Invoice No
            'B' => 12, // Date
            'C' => 20, // Customer
            'D' => 15, // Cashier
            'E' => 12, // Items
            'F' => 15, // Subtotal
            'G' => 15, // Tax
            'H' => 15, // Discount
            'I' => 15, // Service
            'J' => 15, // Grand Total
            'K' => 15, // Payment Method
            'L' => 12, // Status
            'M' => 15, // Paid
            'N' => 15, // Change
            'O' => 25, // Notes
        ];
    }

    public function title(): string
    {
        return 'Laporan Penjualan';
    }
}
