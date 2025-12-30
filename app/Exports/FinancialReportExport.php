<?php

namespace App\Exports;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Expense;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialReportExport implements WithMultipleSheets, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate ?? now()->startOfMonth();
        $this->endDate = $endDate ?? now()->endOfMonth();
    }

    public function sheets(): array
    {
        $sheets = [];

        $sheets[] = new FinancialSummarySheet($this->startDate, $this->endDate);
        $sheets[] = new SalesSheet($this->startDate, $this->endDate);
        $sheets[] = new PurchasesSheet($this->startDate, $this->endDate);
        $sheets[] = new ExpensesSheet($this->startDate, $this->endDate);
        $sheets[] = new ProfitAnalysisSheet($this->startDate, $this->endDate);

        return $sheets;
    }
}

class FinancialSummarySheet implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        // Calculate sales data
        $sales = Sale::whereBetween('sale_date', [$this->startDate, $this->endDate])->get();
        $totalSales = $sales->sum('grand_total');
        $salesCount = $sales->count();
        $averageSale = $salesCount > 0 ? $totalSales / $salesCount : 0;

        // Calculate purchases data
        $purchases = Purchase::whereBetween('purchase_date', [$this->startDate, $this->endDate])->get();
        $totalPurchases = $purchases->sum('grand_total');
        $purchasesCount = $purchases->count();

        // Calculate expenses data
        $expenses = Expense::whereBetween('expense_date', [$this->startDate, $this->endDate])->get();
        $totalExpenses = $expenses->sum('amount');
        $expensesCount = $expenses->count();

        // Calculate profit
        $totalProfit = 0;
        foreach ($sales as $sale) {
            $sale->load('items.product');
            foreach ($sale->items as $item) {
                if ($item->product) {
                    $totalProfit += ($item->unit_price - $item->product->purchase_price) * $item->quantity;
                }
            }
        }

        $netIncome = $totalProfit - $totalExpenses;
        $profitMargin = $totalSales > 0 ? ($totalProfit / $totalSales) * 100 : 0;
        $netMargin = $totalSales > 0 ? ($netIncome / $totalSales) * 100 : 0;

        $data = collect([
            ['KATEGORI', 'NILAI', 'KETERANGAN'],
            ['PENJUALAN', 'Rp ' . number_format($totalSales, 0, ',', '.'), $salesCount . ' transaksi'],
            ['PEMBELIAN', 'Rp ' . number_format($totalPurchases, 0, ',', '.'), $purchasesCount . ' transaksi'],
            ['PENGELUARAN', 'Rp ' . number_format($totalExpenses, 0, ',', '.'), $expensesCount . ' transaksi'],
            ['KEUNTUNGAN KOTOR', 'Rp ' . number_format($totalProfit, 0, ',', '.'), number_format($profitMargin, 2) . '% margin'],
            ['PENDAPATAN BERSIH', 'Rp ' . number_format($netIncome, 0, ',', '.'), number_format($netMargin, 2) . '% margin'],
            ['RATA-RATA PENJUALAN', 'Rp ' . number_format($averageSale, 0, ',', '.'), 'Per transaksi'],
            ['', '', ''],
            ['PERIODE', \Carbon\Carbon::parse($this->startDate)->format('d/m/Y') . ' - ' . \Carbon\Carbon::parse($this->endDate)->format('d/m/Y'), ''],
            ['DIBUAT PADA', now()->format('d/m/Y H:i:s'), ''],
        ]);

        return $data;
    }

    public function headings(): array
    {
        return ['KATEGORI', 'NILAI', 'KETERANGAN'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ],

            // Highlight important rows
            'A2:C6' => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']],
            ],

            // Net income row
            'A6:C6' => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '28A745']],
            ],
        ];
    }

    public function title(): string
    {
        return 'Ringkasan Keuangan';
    }
}

class SalesSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        return Sale::with(['customer', 'user'])
            ->whereBetween('sale_date', [$this->startDate, $this->endDate])
            ->orderBy('sale_date', 'desc')
            ->get();
    }

    public function map($sale): array
    {
        $profit = 0;
        $sale->load('items.product');
        foreach ($sale->items as $item) {
            if ($item->product) {
                $profit += ($item->unit_price - $item->product->purchase_price) * $item->quantity;
            }
        }

        $profitMargin = $sale->grand_total > 0 ? ($profit / $sale->grand_total) * 100 : 0;

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
            ucfirst($sale->payment_method),
            ucfirst($sale->payment_status),
            number_format($profit, 0, ',', '.'),
            number_format($profitMargin, 2) . '%',
        ];
    }

    public function headings(): array
    {
        return [
            'NO. INVOICE',
            'TANGGAL',
            'CUSTOMER',
            'KASIR',
            'ITEMS',
            'SUBTOTAL',
            'PAJAK',
            'DISKON',
            'BIAYA LAYANAN',
            'TOTAL',
            'METODE BAYAR',
            'STATUS',
            'KEUNTUNGAN',
            'MARGIN',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ],

            'F:N' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
        ];
    }

    public function title(): string
    {
        return 'Penjualan';
    }
}

class PurchasesSheet extends PurchasesExport
{
    public function __construct($startDate, $endDate)
    {
        parent::__construct($startDate, $endDate);
    }

    public function title(): string
    {
        return 'Pembelian';
    }
}

class ExpensesSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        return Expense::with('user')
            ->whereBetween('expense_date', [$this->startDate, $this->endDate])
            ->orderBy('expense_date', 'desc')
            ->get();
    }

    public function map($expense): array
    {
        return [
            $expense->expense_number,
            $expense->expense_date->format('d/m/Y'),
            $expense->category,
            $expense->description,
            number_format($expense->amount, 0, ',', '.'),
            $expense->user->name,
            $expense->notes ?? '-',
            $expense->created_at->format('d/m/Y H:i'),
        ];
    }

    public function headings(): array
    {
        return [
            'NO. PENGELUARAN',
            'TANGGAL',
            'KATEGORI',
            'DESKRIPSI',
            'JUMLAH',
            'DIBUAT OLEH',
            'CATATAN',
            'DIBUAT PADA',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ],

            'E' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],
        ];
    }

    public function title(): string
    {
        return 'Pengeluaran';
    }
}

class ProfitAnalysisSheet implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        // Get daily data for the period
        $data = collect();
        $currentDate = \Carbon\Carbon::parse($this->startDate);
        $endDate = \Carbon\Carbon::parse($this->endDate);

        $headers = ['TANGGAL', 'PENJUALAN', 'KEUNTUNGAN', 'PENGELUARAN', 'PENDAPATAN BERSIH', 'MARGIN BERSIH'];
        $data->push($headers);

        $totalSales = 0;
        $totalProfit = 0;
        $totalExpenses = 0;
        $totalNetIncome = 0;

        while ($currentDate <= $endDate) {
            $date = $currentDate->format('Y-m-d');

            // Daily sales
            $dailySales = Sale::whereDate('sale_date', $date)->get();
            $dailySalesAmount = $dailySales->sum('grand_total');

            // Daily profit
            $dailyProfit = 0;
            foreach ($dailySales as $sale) {
                $sale->load('items.product');
                foreach ($sale->items as $item) {
                    if ($item->product) {
                        $dailyProfit += ($item->unit_price - $item->product->purchase_price) * $item->quantity;
                    }
                }
            }

            // Daily expenses
            $dailyExpenses = Expense::whereDate('expense_date', $date)->sum('amount');

            // Daily net income
            $dailyNetIncome = $dailyProfit - $dailyExpenses;
            $dailyNetMargin = $dailySalesAmount > 0 ? ($dailyNetIncome / $dailySalesAmount) * 100 : 0;

            $data->push([
                $currentDate->format('d/m/Y'),
                number_format($dailySalesAmount, 0, ',', '.'),
                number_format($dailyProfit, 0, ',', '.'),
                number_format($dailyExpenses, 0, ',', '.'),
                number_format($dailyNetIncome, 0, ',', '.'),
                number_format($dailyNetMargin, 2) . '%',
            ]);

            $totalSales += $dailySalesAmount;
            $totalProfit += $dailyProfit;
            $totalExpenses += $dailyExpenses;
            $totalNetIncome += $dailyNetIncome;

            $currentDate->addDay();
        }

        // Add summary row
        $data->push([]);
        $data->push([
            'TOTAL',
            'Rp ' . number_format($totalSales, 0, ',', '.'),
            'Rp ' . number_format($totalProfit, 0, ',', '.'),
            'Rp ' . number_format($totalExpenses, 0, ',', '.'),
            'Rp ' . number_format($totalNetIncome, 0, ',', '.'),
            $totalSales > 0 ? number_format(($totalNetIncome / $totalSales) * 100, 2) . '%' : '0%'
        ]);

        return $data;
    }

    public function headings(): array
    {
        return ['TANGGAL', 'PENJUALAN', 'KEUNTUNGAN', 'PENGELUARAN', 'PENDAPATAN BERSIH', 'MARGIN BERSIH'];
    }

    public function styles(Worksheet $sheet)
    {
        $totalRows = $sheet->getHighestRow();
        $summaryRow = $totalRows;

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ],

            'B:F' => ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]],

            $summaryRow => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '28A745']],
            ],
        ];
    }

    public function title(): string
    {
        return 'Analisis Keuntungan';
    }
}
