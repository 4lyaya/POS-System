<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Exports\SalesExport;
use App\Exports\PurchasesExport;
use App\Exports\CustomersExport;
use App\Exports\StockReportExport;
use App\Exports\FinancialReportExport;
use App\Exports\TransactionReportExport;
use App\Pdf\SalesReportPdf;
use App\Pdf\InventoryReportPdf;
use App\Pdf\ReceiptPdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Customer;
use Carbon\Carbon;

class ExportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:export-data');
    }

    // ==================== PRODUCTS ====================

    public function exportProducts(Request $request)
    {
        $request->validate([
            'format' => 'required|in:excel,pdf,csv',
            'category_id' => 'nullable|exists:categories,id',
            'stock_status' => 'nullable|in:all,low,out,normal',
            'is_active' => 'nullable|boolean',
        ]);

        $categoryId = $request->category_id;
        $stockStatus = $request->stock_status;
        $isActive = $request->is_active;

        $filename = 'produk-' . date('Y-m-d-H-i') . '.' . ($request->format === 'excel' ? 'xlsx' : ($request->format === 'pdf' ? 'pdf' : 'csv'));

        if ($request->format === 'excel' || $request->format === 'csv') {
            $export = new ProductsExport($categoryId, $stockStatus, $isActive);

            if ($request->format === 'excel') {
                return Excel::download($export, $filename);
            } else {
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
            }
        } else {
            // PDF export for products
            $products = Product::with(['category', 'unit'])
                ->when($categoryId, function ($query) use ($categoryId) {
                    return $query->where('category_id', $categoryId);
                })
                ->when($stockStatus, function ($query) use ($stockStatus) {
                    if ($stockStatus === 'low') {
                        return $query->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0);
                    } elseif ($stockStatus === 'out') {
                        return $query->where('stock', '<=', 0);
                    } elseif ($stockStatus === 'normal') {
                        return $query->whereColumn('stock', '>', 'min_stock');
                    }
                })
                ->when(!is_null($isActive), function ($query) use ($isActive) {
                    return $query->where('is_active', $isActive);
                })
                ->orderBy('name')
                ->get();

            $pdf = new InventoryReportPdf($products, 'Laporan Produk');
            $pdfContent = $pdf->generate();

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }
    }

    // ==================== SALES ====================

    public function exportSales(Request $request)
    {
        $request->validate([
            'format' => 'required|in:excel,pdf,csv',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,id',
            'payment_method' => 'nullable|string',
            'payment_status' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $userId = $request->user_id;
        $paymentMethod = $request->payment_method;
        $paymentStatus = $request->payment_status;
        $customerId = $request->customer_id;

        $filename = 'penjualan-' . date('Y-m-d-H-i') . '.' . ($request->format === 'excel' ? 'xlsx' : ($request->format === 'pdf' ? 'pdf' : 'csv'));

        if ($request->format === 'excel' || $request->format === 'csv') {
            $export = new SalesExport($startDate, $endDate, $userId, $paymentMethod, $paymentStatus, $customerId);

            if ($request->format === 'excel') {
                return Excel::download($export, $filename);
            } else {
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
            }
        } else {
            // PDF export for sales
            $pdf = new SalesReportPdf($startDate, $endDate, $userId, $paymentMethod, $paymentStatus, $customerId);
            $pdfContent = $pdf->generate();

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }
    }

    // ==================== PURCHASES ====================

    public function exportPurchases(Request $request)
    {
        $request->validate([
            'format' => 'required|in:excel,pdf,csv',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'payment_status' => 'nullable|string',
        ]);

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $supplierId = $request->supplier_id;
        $paymentStatus = $request->payment_status;

        $filename = 'pembelian-' . date('Y-m-d-H-i') . '.' . ($request->format === 'excel' ? 'xlsx' : ($request->format === 'pdf' ? 'pdf' : 'csv'));

        if ($request->format === 'excel' || $request->format === 'csv') {
            $export = new PurchasesExport($startDate, $endDate, $supplierId, $paymentStatus);

            if ($request->format === 'excel') {
                return Excel::download($export, $filename);
            } else {
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
            }
        } else {
            // PDF export for purchases (you need to create PurchasesReportPdf)
            return redirect()->back()
                ->with('error', 'PDF export untuk pembelian belum tersedia');
        }
    }

    // ==================== CUSTOMERS ====================

    public function exportCustomers(Request $request)
    {
        $request->validate([
            'format' => 'required|in:excel,pdf,csv',
            'is_active' => 'nullable|boolean',
            'has_points' => 'nullable|boolean',
        ]);

        $isActive = $request->is_active;
        $hasPoints = $request->has_points;

        $filename = 'pelanggan-' . date('Y-m-d-H-i') . '.' . ($request->format === 'excel' ? 'xlsx' : ($request->format === 'pdf' ? 'pdf' : 'csv'));

        if ($request->format === 'excel' || $request->format === 'csv') {
            $export = new CustomersExport($isActive, $hasPoints);

            if ($request->format === 'excel') {
                return Excel::download($export, $filename);
            } else {
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
            }
        } else {
            return redirect()->back()
                ->with('error', 'PDF export untuk pelanggan belum tersedia');
        }
    }

    // ==================== STOCK REPORT ====================

    public function exportStockReport(Request $request)
    {
        $request->validate([
            'format' => 'required|in:excel,pdf,csv',
            'category_id' => 'nullable|exists:categories,id',
            'low_stock_only' => 'nullable|boolean',
            'out_of_stock_only' => 'nullable|boolean',
        ]);

        $categoryId = $request->category_id;
        $lowStockOnly = $request->boolean('low_stock_only');
        $outOfStockOnly = $request->boolean('out_of_stock_only');

        $filename = 'laporan-stok-' . date('Y-m-d-H-i') . '.' . ($request->format === 'excel' ? 'xlsx' : ($request->format === 'pdf' ? 'pdf' : 'csv'));

        if ($request->format === 'excel' || $request->format === 'csv') {
            $export = new StockReportExport($categoryId, $lowStockOnly, $outOfStockOnly);

            if ($request->format === 'excel') {
                return Excel::download($export, $filename);
            } else {
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
            }
        } else {
            // PDF export for stock report
            $products = Product::with(['category', 'unit'])
                ->when($categoryId, function ($query) use ($categoryId) {
                    return $query->where('category_id', $categoryId);
                })
                ->when($lowStockOnly, function ($query) {
                    return $query->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0);
                })
                ->when($outOfStockOnly, function ($query) {
                    return $query->where('stock', '<=', 0);
                })
                ->orderBy('category_id')
                ->orderBy('name')
                ->get();

            $title = 'Laporan Stok';
            if ($lowStockOnly) $title .= ' (Stok Menipis)';
            if ($outOfStockOnly) $title .= ' (Stok Habis)';

            $pdf = new InventoryReportPdf($products, $title);
            $pdfContent = $pdf->generate();

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }
    }

    // ==================== FINANCIAL REPORT ====================

    public function exportFinancialReport(Request $request)
    {
        $request->validate([
            'format' => 'required|in:excel,pdf,csv',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'report_type' => 'required|in:income,expense,profit',
        ]);

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $reportType = $request->report_type;

        $filename = 'laporan-keuangan-' . $reportType . '-' . date('Y-m-d-H-i') . '.' .
            ($request->format === 'excel' ? 'xlsx' : ($request->format === 'pdf' ? 'pdf' : 'csv'));

        if ($request->format === 'excel' || $request->format === 'csv') {
            $export = new FinancialReportExport($startDate, $endDate, $reportType);

            if ($request->format === 'excel') {
                return Excel::download($export, $filename);
            } else {
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
            }
        } else {
            return redirect()->back()
                ->with('error', 'PDF export untuk laporan keuangan belum tersedia');
        }
    }

    // ==================== TRANSACTION REPORT ====================

    public function exportTransactionReport(Request $request)
    {
        $request->validate([
            'format' => 'required|in:excel,pdf,csv',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'transaction_type' => 'required|in:sales,purchases,both',
        ]);

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $transactionType = $request->transaction_type;

        $filename = 'laporan-transaksi-' . $transactionType . '-' . date('Y-m-d-H-i') . '.' .
            ($request->format === 'excel' ? 'xlsx' : ($request->format === 'pdf' ? 'pdf' : 'csv'));

        if ($request->format === 'excel' || $request->format === 'csv') {
            $export = new TransactionReportExport($startDate, $endDate, $transactionType);

            if ($request->format === 'excel') {
                return Excel::download($export, $filename);
            } else {
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
            }
        } else {
            return redirect()->back()
                ->with('error', 'PDF export untuk laporan transaksi belum tersedia');
        }
    }

    // ==================== INDIVIDUAL RECEIPT ====================

    public function exportReceipt($id)
    {
        $sale = Sale::with(['customer', 'user', 'items.product'])->findOrFail($id);

        $pdf = new ReceiptPdf($sale);
        $filename = 'struk-' . $sale->invoice_number . '.pdf';

        return response($pdf->generate(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function downloadReceipt($id)
    {
        $sale = Sale::with(['customer', 'user', 'items.product'])->findOrFail($id);

        $pdf = new ReceiptPdf($sale);
        $filename = 'struk-' . $sale->invoice_number . '.pdf';

        $pdf->download($filename);
    }

    // ==================== BULK RECEIPT EXPORT ====================

    public function exportBulkReceipts(Request $request)
    {
        $request->validate([
            'sale_ids' => 'required|array',
            'sale_ids.*' => 'exists:sales,id',
        ]);

        $sales = Sale::with(['customer', 'user', 'items.product'])
            ->whereIn('id', $request->sale_ids)
            ->get();

        if ($sales->isEmpty()) {
            return redirect()->back()
                ->with('error', 'Tidak ada transaksi yang dipilih');
        }

        // Create a combined PDF
        $combinedPdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => [80, 297],
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_header' => 0,
            'margin_footer' => 0,
            'default_font' => 'courier',
        ]);

        foreach ($sales as $sale) {
            $receiptPdf = new ReceiptPdf($sale);
            $combinedPdf->WriteHTML($receiptPdf->generate());

            // Add page break except for last receipt
            if ($sale->id !== $sales->last()->id) {
                $combinedPdf->AddPage();
            }
        }

        $filename = 'struk-bulk-' . date('Y-m-d-H-i') . '.pdf';
        $pdfContent = $combinedPdf->Output('', 'S');

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ==================== TEMPLATE DOWNLOAD ====================

    public function downloadTemplate($type)
    {
        $templates = [
            'products' => [
                'filename' => 'template-import-produk.xlsx',
                'headers' => [
                    'code',
                    'barcode',
                    'name',
                    'category_id',
                    'unit_id',
                    'purchase_price',
                    'selling_price',
                    'stock',
                    'min_stock',
                    'description',
                    'is_active'
                ],
                'example' => [
                    'PRD-001',
                    '1234567890123',
                    'Produk Contoh',
                    '1',
                    '1',
                    '10000',
                    '15000',
                    '100',
                    '10',
                    'Deskripsi produk',
                    '1'
                ]
            ],
            'customers' => [
                'filename' => 'template-import-pelanggan.xlsx',
                'headers' => ['name', 'email', 'phone', 'address', 'birth_date', 'gender', 'is_active'],
                'example' => [
                    'John Doe',
                    'john@example.com',
                    '081234567890',
                    'Jl. Contoh No. 123',
                    '1990-01-01',
                    'male',
                    '1'
                ]
            ],
            'suppliers' => [
                'filename' => 'template-import-supplier.xlsx',
                'headers' => ['name', 'email', 'phone', 'address', 'contact_person', 'tax_number', 'is_active'],
                'example' => [
                    'Supplier Contoh',
                    'supplier@example.com',
                    '081234567891',
                    'Jl. Supplier No. 456',
                    'Contact Person',
                    '123456789',
                    '1'
                ]
            ]
        ];

        if (!array_key_exists($type, $templates)) {
            return redirect()->back()
                ->with('error', 'Template tidak tersedia');
        }

        $template = $templates[$type];

        return Excel::download(new class($template) implements \Maatwebsite\Excel\Concerns\FromArray {
            private $template;

            public function __construct($template)
            {
                $this->template = $template;
            }

            public function array(): array
            {
                return [
                    $this->template['headers'],
                    $this->template['example']
                ];
            }
        }, $template['filename']);
    }
}
