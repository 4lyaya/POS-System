<?php

namespace App\Http\Controllers;

use App\Http\Requests\Report\GenerateReportRequest;
use App\Services\Report\SalesReportService;
use App\Services\Report\InventoryReportService;
use App\Services\Report\FinancialReportService;
use App\Exports\SalesExport;
use App\Exports\StockReportExport;
use App\Exports\FinancialReportExport;
use App\Pdf\SalesReportPdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected $salesReportService;
    protected $inventoryReportService;
    protected $financialReportService;

    public function __construct(
        SalesReportService $salesReportService,
        InventoryReportService $inventoryReportService,
        FinancialReportService $financialReportService
    ) {
        $this->salesReportService = $salesReportService;
        $this->inventoryReportService = $inventoryReportService;
        $this->financialReportService = $financialReportService;
        $this->middleware('permission:view-reports');
    }

    public function sales(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $report = $this->salesReportService->generate($startDate, $endDate, [
            'user_id' => $request->user_id,
            'payment_method' => $request->payment_method,
            'customer_id' => $request->customer_id,
        ]);

        return Inertia::render('Reports/Sales', [
            'report' => $report,
            'filters' => $request->only(['start_date', 'end_date', 'user_id', 'payment_method', 'customer_id']),
        ]);
    }

    public function inventory(Request $request)
    {
        $report = $this->inventoryReportService->generate([
            'category_id' => $request->category_id,
            'stock_status' => $request->stock_status,
            'low_stock_only' => $request->boolean('low_stock_only'),
        ]);

        return Inertia::render('Reports/Inventory', [
            'report' => $report,
            'filters' => $request->only(['category_id', 'stock_status', 'low_stock_only']),
        ]);
    }

    public function financial(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $report = $this->financialReportService->generate($startDate, $endDate);

        return Inertia::render('Reports/Financial', [
            'report' => $report,
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    public function export(GenerateReportRequest $request)
    {
        $type = $request->type;
        $format = $request->format;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $filename = $type . '-report-' . date('Y-m-d-H-i') . '.' . ($format === 'excel' ? 'xlsx' : 'pdf');

        switch ($type) {
            case 'sales':
                if ($format === 'excel') {
                    return Excel::download(
                        new SalesExport($startDate, $endDate, $request->user_id, $request->payment_method),
                        $filename
                    );
                } else {
                    $pdf = new SalesReportPdf($startDate, $endDate, $request->user_id, $request->payment_method);
                    $pdfContent = $pdf->generate();

                    return response($pdfContent, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    ]);
                }
                break;

            case 'stock':
                if ($format === 'excel') {
                    return Excel::download(
                        new StockReportExport($request->category_id, $request->boolean('low_stock_only')),
                        $filename
                    );
                }
                break;

            case 'financial':
                if ($format === 'excel') {
                    return Excel::download(
                        new FinancialReportExport($startDate, $endDate),
                        $filename
                    );
                }
                break;
        }

        return redirect()->back()
            ->with('error', 'Format ekspor tidak tersedia untuk laporan ini');
    }

    public function dashboardStats(Request $request)
    {
        $period = $request->input('period', 'today');

        $stats = $this->salesReportService->getDashboardStats($period);

        return response()->json($stats);
    }

    public function salesChart(Request $request)
    {
        $period = $request->input('period', 'week');

        $chartData = $this->salesReportService->getSalesChartData($period);

        return response()->json($chartData);
    }
}
