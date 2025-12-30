<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, ShouldAutoSize
{
    protected $hasPoints;
    protected $activeOnly;

    public function __construct($hasPoints = false, $activeOnly = true)
    {
        $this->hasPoints = $hasPoints;
        $this->activeOnly = $activeOnly;
    }

    public function collection()
    {
        $query = Customer::withCount(['sales as total_transactions'])
            ->withSum(['sales as total_spent'], 'grand_total');

        if ($this->hasPoints) {
            $query->where('points', '>', 0);
        }

        if ($this->activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')
            ->get();
    }

    public function map($customer): array
    {
        $lastPurchase = $customer->last_purchase
            ? \Carbon\Carbon::parse($customer->last_purchase)->format('d/m/Y')
            : '-';

        $averageSpent = $customer->total_transactions > 0
            ? $customer->total_spent / $customer->total_transactions
            : 0;

        $membershipTier = 'Reguler';
        if ($customer->total_spent >= 5000000) {
            $membershipTier = 'Gold';
        } elseif ($customer->total_spent >= 2000000) {
            $membershipTier = 'Silver';
        } elseif ($customer->total_spent >= 500000) {
            $membershipTier = 'Bronze';
        }

        return [
            $customer->member_id ?? '-',
            $customer->name,
            $customer->email ?? '-',
            $customer->phone ?? '-',
            $customer->gender == 'male' ? 'Laki-laki' : ($customer->gender == 'female' ? 'Perempuan' : 'Lainnya'),
            $customer->birth_date ? \Carbon\Carbon::parse($customer->birth_date)->format('d/m/Y') : '-',
            $customer->address ?? '-',
            $customer->points,
            number_format($customer->total_spent ?? 0, 0, ',', '.'),
            $customer->total_transactions ?? 0,
            number_format($averageSpent, 0, ',', '.'),
            $lastPurchase,
            $membershipTier,
            $customer->is_active ? 'Aktif' : 'Nonaktif',
            $customer->created_at->format('d/m/Y H:i'),
            $customer->updated_at->format('d/m/Y H:i'),
        ];
    }

    public function headings(): array
    {
        return [
            'ID MEMBER',
            'NAMA',
            'EMAIL',
            'TELEPON',
            'JENIS KELAMIN',
            'TANGGAL LAHIR',
            'ALAMAT',
            'POINTS',
            'TOTAL BELANJA',
            'TOTAL TRANSAKSI',
            'RATA-RATA BELANJA',
            'PEMBELIAN TERAKHIR',
            'TINGKAT MEMBER',
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
            'H' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'I' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'J' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
            'K' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],

            // Membership tier colors
            'M' => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ],

            // Auto size all columns
            'A:P' => [
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
            'A' => 15, // ID Member
            'B' => 25, // Nama
            'C' => 25, // Email
            'D' => 15, // Telepon
            'E' => 15, // Jenis Kelamin
            'F' => 15, // Tanggal Lahir
            'G' => 30, // Alamat
            'H' => 12, // Points
            'I' => 18, // Total Belanja
            'J' => 15, // Total Transaksi
            'K' => 18, // Rata-rata Belanja
            'L' => 18, // Pembelian Terakhir
            'M' => 15, // Tingkat Member
            'N' => 12, // Status Aktif
            'O' => 18, // Dibuat Pada
            'P' => 18, // Diperbarui Pada
        ];
    }

    public function title(): string
    {
        $title = 'Data Pelanggan';

        if ($this->hasPoints) {
            $title .= ' (Memiliki Points)';
        }

        if (!$this->activeOnly) {
            $title .= ' (Semua Status)';
        }

        return $title;
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Set auto filter
                $sheet->setAutoFilter('A1:P1');

                // Freeze first row
                $sheet->freezePane('A2');

                // Add summary row
                $totalRows = $sheet->getHighestRow();
                $summaryRow = $totalRows + 2;

                // Calculate totals
                $totalCustomers = $totalRows - 1;
                $totalPoints = 0;
                $totalSpent = 0;
                $totalTransactions = 0;
                $activeCustomers = 0;

                for ($row = 2; $row <= $totalRows; $row++) {
                    $points = $sheet->getCell('H' . $row)->getValue();
                    $spent = $sheet->getCell('I' . $row)->getValue();
                    $transactions = $sheet->getCell('J' . $row)->getValue();
                    $status = $sheet->getCell('N' . $row)->getValue();

                    $totalPoints += (int)$points;
                    $totalSpent += (float)str_replace(['.', ','], '', $spent);
                    $totalTransactions += (int)$transactions;

                    if ($status === 'Aktif') {
                        $activeCustomers++;
                    }
                }

                // Calculate averages
                $averagePoints = $totalCustomers > 0 ? $totalPoints / $totalCustomers : 0;
                $averageSpent = $totalTransactions > 0 ? $totalSpent / $totalTransactions : 0;

                // Add summary
                $sheet->setCellValue('A' . $summaryRow, 'RINGKASAN');
                $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true);

                $sheet->setCellValue('A' . ($summaryRow + 1), 'Total Pelanggan:');
                $sheet->setCellValue('B' . ($summaryRow + 1), $totalCustomers);

                $sheet->setCellValue('A' . ($summaryRow + 2), 'Pelanggan Aktif:');
                $sheet->setCellValue('B' . ($summaryRow + 2), $activeCustomers);

                $sheet->setCellValue('A' . ($summaryRow + 3), 'Total Points:');
                $sheet->setCellValue('B' . ($summaryRow + 3), number_format($totalPoints, 0, ',', '.'));

                $sheet->setCellValue('A' . ($summaryRow + 4), 'Rata-rata Points:');
                $sheet->setCellValue('B' . ($summaryRow + 4), number_format($averagePoints, 1));

                $sheet->setCellValue('A' . ($summaryRow + 5), 'Total Belanja:');
                $sheet->setCellValue('B' . ($summaryRow + 5), 'Rp ' . number_format($totalSpent, 0, ',', '.'));

                $sheet->setCellValue('A' . ($summaryRow + 6), 'Total Transaksi:');
                $sheet->setCellValue('B' . ($summaryRow + 6), $totalTransactions);

                $sheet->setCellValue('A' . ($summaryRow + 7), 'Rata-rata Belanja per Transaksi:');
                $sheet->setCellValue('B' . ($summaryRow + 7), 'Rp ' . number_format($averageSpent, 0, ',', '.'));

                // Style summary
                $summaryRange = 'A' . $summaryRow . ':B' . ($summaryRow + 7);
                $sheet->getStyle($summaryRange)->getFont()->setBold(true);
                $sheet->getStyle($summaryRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $sheet->getStyle($summaryRange)->getFill()->getStartColor()->setARGB('FFF2F2F2');

                // Color code membership tiers
                for ($row = 2; $row <= $totalRows; $row++) {
                    $tier = $sheet->getCell('M' . $row)->getValue();

                    $color = match ($tier) {
                        'Gold' => 'FFD4AF37',
                        'Silver' => 'FFC0C0C0',
                        'Bronze' => 'FFCD7F32',
                        default => 'FFFFFFFF',
                    };

                    $sheet->getStyle('M' . $row)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($color);
                }

                // Add generated at info
                $generatedRow = $summaryRow + 9;
                $sheet->setCellValue('A' . $generatedRow, 'Dibuat pada: ' . now()->format('d/m/Y H:i:s'));
                $sheet->mergeCells('A' . $generatedRow . ':C' . $generatedRow);
                $sheet->getStyle('A' . $generatedRow)->getFont()->setItalic(true);
            },
        ];
    }
}
