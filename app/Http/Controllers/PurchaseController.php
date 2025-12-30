<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchase\StorePurchaseRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Inventory\PurchaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    protected $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
        $this->middleware('permission:manage-purchases');
    }

    public function index(Request $request)
    {
        $query = Purchase::with(['supplier', 'user']);

        // Date range filter
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('purchase_date', [$request->start_date, $request->end_date]);
        } else {
            // Default to this month
            $query->whereMonth('purchase_date', now()->month)
                ->whereYear('purchase_date', now()->year);
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

        $purchases = $query->orderBy('purchase_date', 'desc')->paginate(20);
        $suppliers = Supplier::active()->get();

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'start_date', 'end_date', 'payment_status', 'supplier_id']),
        ]);
    }

    public function create()
    {
        $suppliers = Supplier::active()->get();
        $products = Product::active()->with(['category', 'unit'])->get();

        return Inertia::render('Purchases/Create', [
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    public function store(StorePurchaseRequest $request)
    {
        try {
            $purchase = $this->purchaseService->processPurchase($request->validated());

            return redirect()->route('purchases.show', $purchase)
                ->with('success', 'Pembelian berhasil diproses');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memproses pembelian: ' . $e->getMessage());
        }
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'user', 'items.product']);

        return Inertia::render('Purchases/Show', [
            'purchase' => $purchase,
        ]);
    }

    public function edit(Purchase $purchase)
    {
        // Only allow edit if not fully paid
        if ($purchase->payment_status === 'paid') {
            return redirect()->route('purchases.show', $purchase)
                ->with('error', 'Pembelian yang sudah lunas tidak dapat diedit');
        }

        $purchase->load(['items.product']);
        $suppliers = Supplier::active()->get();
        $products = Product::active()->with(['category', 'unit'])->get();

        return Inertia::render('Purchases/Edit', [
            'purchase' => $purchase,
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase)
    {
        try {
            // Only allow update if not fully paid
            if ($purchase->payment_status === 'paid') {
                return redirect()->back()
                    ->with('error', 'Pembelian yang sudah lunas tidak dapat diperbarui');
            }

            // Update logic here (simplified - in real app you'd need to handle stock adjustments)
            $purchase->update($request->validated());

            return redirect()->route('purchases.show', $purchase)
                ->with('success', 'Pembelian berhasil diperbarui');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui pembelian: ' . $e->getMessage());
        }
    }

    public function addPayment(Request $request, Purchase $purchase)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:' . $purchase->due_amount,
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->purchaseService->updatePayment($purchase, [
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'notes' => $request->notes,
            ]);

            return redirect()->route('purchases.show', $purchase)
                ->with('success', 'Pembayaran berhasil dicatat');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal mencatat pembayaran: ' . $e->getMessage());
        }
    }
}
