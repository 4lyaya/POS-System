<?php

namespace App\Services;

use App\Exports\SalesExport;
use App\Exports\ProductsExport;
use App\Exports\PurchasesExport;
use App\Exports\CustomersExport;
use App\Exports\StockReportExport;
use App\Exports\FinancialReportExport;
use App\Pdf\SalesReportPdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class ExportService
{
    public function exportSales($startDate, $endDate, $format = 'excel', $filters = [])
    {
        $export = new SalesExport($startDate, $endDate, $filters['user_id'] ?? null, $filters['payment_method'] ?? null);

        if ($format === 'excel') {
            $filename = 'sales-report-' . date('Y-m-d-H-i') . '.xlsx';
            return Excel::download($export, $filename);
        } elseif ($format === 'pdf') {
            $pdf = new SalesReportPdf($startDate, $endDate, $filters['user_id'] ?? null, $filters['payment_method'] ?? null);
            $pdfContent = $pdf->generate();

            $filename = 'sales-report-' . date('Y-m-d-H-i') . '.pdf';

            // Save to storage
            Storage::put('reports/' . $filename, $pdfContent);

            return [
                'path' => storage_path('app/reports/' . $filename),
                'filename' => $filename,
                'content' => $pdfContent,
            ];
        }

        throw new \Exception('Format ekspor tidak didukung');
    }

    public function exportProducts($filters = [], $format = 'excel')
    {
        $export = new ProductsExport(
            $filters['category_id'] ?? null,
            $filters['stock_status'] ?? null
        );

        if ($format === 'excel') {
            $filename = 'products-report-' . date('Y-m-d-H-i') . '.xlsx';
            return Excel::download($export, $filename);
        }

        throw new \Exception('Format ekspor tidak didukung untuk produk');
    }

    public function exportStockReport($filters = [], $format = 'excel')
    {
        $export = new StockReportExport(
            $filters['category_id'] ?? null,
            $filters['low_stock_only'] ?? false
        );

        if ($format === 'excel') {
            $filename = 'stock-report-' . date('Y-m-d-H-i') . '.xlsx';
            return Excel::download($export, $filename);
        }

        throw new \Exception('Format ekspor tidak didukung untuk laporan stok');
    }

    public function exportFinancialReport($startDate, $endDate, $format = 'excel')
    {
        $export = new FinancialReportExport($startDate, $endDate);

        if ($format === 'excel') {
            $filename = 'financial-report-' . date('Y-m-d-H-i') . '.xlsx';
            return Excel::download($export, $filename);
        }

        throw new \Exception('Format ekspor tidak didukung untuk laporan finansial');
    }

    public function exportPurchases($startDate, $endDate, $filters = [], $format = 'excel')
    {
        $export = new PurchasesExport($startDate, $endDate, $filters['supplier_id'] ?? null);

        if ($format === 'excel') {
            $filename = 'purchases-report-' . date('Y-m-d-H-i') . '.xlsx';
            return Excel::download($export, $filename);
        }

        throw new \Exception('Format ekspor tidak didukung untuk pembelian');
    }

    public function exportCustomers($filters = [], $format = 'excel')
    {
        $export = new CustomersExport($filters['has_points'] ?? false);

        if ($format === 'excel') {
            $filename = 'customers-report-' . date('Y-m-d-H-i') . '.xlsx';
            return Excel::download($export, $filename);
        }

        throw new \Exception('Format ekspor tidak didukung untuk pelanggan');
    }

    public function generateDailySalesReport($date = null)
    {
        $date = $date ?? today();
        $startDate = $date;
        $endDate = $date;

        $export = new SalesExport($startDate, $endDate);
        $filename = 'daily-sales-' . $date->format('Y-m-d') . '.xlsx';

        return Excel::download($export, $filename);
    }

    public function generateMonthlySalesReport($year = null, $month = null)
    {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');

        $startDate = date("{$year}-{$month}-01");
        $endDate = date("{$year}-{$month}-t", strtotime($startDate));

        $export = new SalesExport($startDate, $endDate);
        $filename = 'monthly-sales-' . $year . '-' . $month . '.xlsx';

        return Excel::download($export, $filename);
    }

    public function getAvailableExportFormats($reportType)
    {
        $formats = [
            'sales' => ['excel', 'pdf'],
            'products' => ['excel'],
            'stock' => ['excel'],
            'financial' => ['excel'],
            'purchases' => ['excel'],
            'customers' => ['excel'],
        ];

        return $formats[$reportType] ?? ['excel'];
    }
}
