<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Inventory\PurchaseService;
use Illuminate\Http\Request;

class PurchaseApiController extends Controller
{
    protected $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
    }

    public function index(Request $request)
    {
        $query = Purchase::with(['supplier', 'user', 'items.product']);

        // Date range filter
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('purchase_date', [$request->start_date, $request->end_date]);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by payment status
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by supplier
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $purchases = $query->orderBy('purchase_date', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $purchases,
        ]);
    }

    public function show($id)
    {
        $purchase = Purchase::with(['supplier', 'user', 'items.product'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $purchase,
        ]);
    }

    public function store(StorePurchaseRequest $request)
    {
        try {
            $purchase = $this->purchaseService->processPurchase($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Pembelian berhasil diproses',
                'data' => $purchase->load(['supplier', 'items.product']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pembelian: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function update(UpdatePurchaseRequest $request, $id)
    {
        try {
            $purchase = Purchase::findOrFail($id);

            // Only allow update if not fully paid
            if ($purchase->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Pembelian yang sudah lunas tidak dapat diperbarui',
                ], 422);
            }

            $purchase->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Pembelian berhasil diperbarui',
                'data' => $purchase,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui pembelian: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function destroy($id)
    {
        try {
            $purchase = Purchase::findOrFail($id);

            // Check if purchase has stock mutations
            if ($purchase->stockMutations()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pembelian tidak dapat dihapus karena sudah mempengaruhi stok',
                ], 422);
            }

            $purchase->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pembelian berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pembelian: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function addPayment(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $purchase = Purchase::findOrFail($id);

            $paymentData = [
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'notes' => $request->notes,
            ];

            $purchase = $this->purchaseService->updatePayment($purchase, $paymentData);

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil dicatat',
                'data' => $purchase,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat pembayaran: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function getUnpaidPurchases()
    {
        $purchases = Purchase::with(['supplier', 'user'])
            ->where('payment_status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $purchases,
        ]);
    }

    public function getPurchasesBySupplier($supplierId)
    {
        $purchases = Purchase::with(['user', 'items.product'])
            ->where('supplier_id', $supplierId)
            ->orderBy('purchase_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $purchases,
        ]);
    }
}
