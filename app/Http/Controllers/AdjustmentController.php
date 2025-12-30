<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreAdjustmentRequest;
use App\Models\Adjustment;
use App\Models\Product;
use App\Services\Inventory\StockService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdjustmentController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
        $this->middleware('permission:manage-stock');
    }

    public function index(Request $request)
    {
        $query = Adjustment::with(['user', 'items.product']);

        // Date range filter
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('adjustment_date', [$request->start_date, $request->end_date]);
        } else {
            // Default to this month
            $query->whereMonth('adjustment_date', now()->month)
                ->whereYear('adjustment_date', now()->year);
        }

        // Filter by adjustment type
        if ($request->has('adjustment_type')) {
            $query->where('adjustment_type', $request->adjustment_type);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('adjustment_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $adjustments = $query->orderBy('adjustment_date', 'desc')->paginate(20);

        return Inertia::render('Adjustments/Index', [
            'adjustments' => $adjustments,
            'filters' => $request->only(['search', 'start_date', 'end_date', 'adjustment_type']),
        ]);
    }

    public function create()
    {
        $products = Product::active()->with(['category', 'unit'])->get();

        return Inertia::render('Adjustments/Create', [
            'products' => $products,
        ]);
    }

    public function store(StoreAdjustmentRequest $request)
    {
        try {
            $adjustment = $this->stockService->createAdjustment($request->validated());

            return redirect()->route('adjustments.show', $adjustment)
                ->with('success', 'Penyesuaian stok berhasil dibuat');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal membuat penyesuaian stok: ' . $e->getMessage());
        }
    }

    public function show(Adjustment $adjustment)
    {
        $adjustment->load(['user', 'items.product', 'stockMutations.product']);

        return Inertia::render('Adjustments/Show', [
            'adjustment' => $adjustment,
        ]);
    }

    public function destroy(Adjustment $adjustment)
    {
        try {
            // Check if adjustment has stock mutations
            if ($adjustment->stockMutations()->exists()) {
                // Reverse stock mutations
                foreach ($adjustment->stockMutations as $mutation) {
                    $product = $mutation->product;

                    if ($mutation->mutation_type === 'in') {
                        // Reverse addition by subtracting
                        if ($product->stock < $mutation->quantity) {
                            return redirect()->back()
                                ->with('error', "Tidak dapat menghapus penyesuaian. Stok {$product->name} tidak mencukupi untuk pengembalian.");
                        }
                        $product->decrement('stock', $mutation->quantity);
                    } elseif ($mutation->mutation_type === 'out') {
                        // Reverse subtraction by adding
                        $product->increment('stock', $mutation->quantity);
                    }

                    // Delete the stock mutation
                    $mutation->delete();
                }
            }

            // Delete adjustment items
            $adjustment->items()->delete();

            // Delete adjustment
            $adjustment->delete();

            return redirect()->route('adjustments.index')
                ->with('success', 'Penyesuaian stok berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menghapus penyesuaian stok: ' . $e->getMessage());
        }
    }

    public function getAdjustmentSummary(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $summary = Adjustment::whereBetween('adjustment_date', [$startDate, $endDate])
            ->selectRaw('
                adjustment_type,
                COUNT(*) as count,
                SUM(
                    SELECT SUM(quantity) 
                    FROM adjustment_items 
                    WHERE adjustment_id = adjustments.id
                ) as total_quantity
            ')
            ->groupBy('adjustment_type')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}
