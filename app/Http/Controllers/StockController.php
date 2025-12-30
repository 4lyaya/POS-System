<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\StockMutation;
use Illuminate\Support\Facades\DB;
use App\Services\Inventory\StockService;

class StockController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
        $this->middleware('permission:manage-stock');
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'unit']);

        // Filter by stock status
        if ($request->has('stock_status')) {
            switch ($request->stock_status) {
                case 'low':
                    $query->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0);
                    break;
                case 'out':
                    $query->where('stock', '<=', 0);
                    break;
                case 'normal':
                    $query->whereColumn('stock', '>', 'min_stock');
                    break;
            }
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('stock', 'asc')->paginate(20);
        $categories = \App\Models\Category::active()->get();

        // Get stock summary
        $summary = [
            'total_products' => Product::count(),
            'total_stock' => Product::sum('stock'),
            'stock_value' => Product::sum(DB::raw('stock * purchase_price')),
            'low_stock_count' => Product::whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0)->count(),
            'out_of_stock_count' => Product::where('stock', '<=', 0)->count(),
        ];

        return Inertia::render('Stock/Index', [
            'products' => $products,
            'categories' => $categories,
            'summary' => $summary,
            'filters' => $request->only(['search', 'category_id', 'stock_status']),
        ]);
    }

    public function history(Request $request, Product $product)
    {
        $query = StockMutation::where('product_id', $product->id);

        // Date range filter
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // Filter by mutation type
        if ($request->has('mutation_type')) {
            $query->where('mutation_type', $request->mutation_type);
        }

        $mutations = $query->with(['user', 'reference'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Stock/History', [
            'product' => $product->load(['category', 'unit']),
            'mutations' => $mutations,
            'filters' => $request->only(['start_date', 'end_date', 'mutation_type']),
        ]);
    }

    public function adjust(Request $request, Product $product)
    {
        $request->validate([
            'adjustment_type' => 'required|in:addition,subtraction,correction',
            'quantity' => 'required|integer|min:1',
            'notes' => 'required|string|max:500',
        ]);

        try {
            $data = [
                'adjustment_type' => $request->adjustment_type,
                'reason' => $request->notes,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => $request->quantity,
                        'notes' => $request->notes,
                    ]
                ]
            ];

            $adjustment = $this->stockService->createAdjustment($data);

            return redirect()->route('stock.history', $product)
                ->with('success', 'Stok berhasil disesuaikan');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menyesuaikan stok: ' . $e->getMessage());
        }
    }

    public function getLowStockAlerts()
    {
        $alerts = $this->stockService->getStockAlert();

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    public function getStockValueAnalysis()
    {
        $analysis = $this->stockService->getStockValueAnalysis();

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    public function exportStockReport(Request $request)
    {
        $request->validate([
            'format' => 'required|in:pdf,excel',
            'category_id' => 'nullable|exists:categories,id',
            'low_stock_only' => 'boolean',
        ]);

        try {
            $filters = [
                'category_id' => $request->category_id,
                'low_stock_only' => $request->boolean('low_stock_only'),
            ];

            if ($request->format === 'excel') {
                $export = new \App\Exports\StockReportExport(
                    $request->category_id,
                    $request->boolean('low_stock_only')
                );

                $filename = 'stock-report-' . date('Y-m-d-H-i') . '.xlsx';

                return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
            } else {
                // PDF export logic here
                return redirect()->back()
                    ->with('error', 'Export PDF untuk stok belum tersedia');
            }
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal mengexport laporan: ' . $e->getMessage());
        }
    }
}
