<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchase\StoreSupplierRequest;
use App\Http\Requests\Purchase\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage-suppliers');
    }

    public function index(Request $request)
    {
        $suppliers = Supplier::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->when($request->has_debt, function ($query) {
                $query->has('purchases', '>', 0)
                    ->whereHas('purchases', function ($q) {
                        $q->where('payment_status', '!=', 'paid');
                    });
            })
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'has_debt']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Suppliers/Create');
    }

    public function store(StoreSupplierRequest $request)
    {
        try {
            Supplier::create($request->validated());

            return redirect()->route('suppliers.index')
                ->with('success', 'Supplier berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal menambahkan supplier: ' . $e->getMessage());
        }
    }

    public function show(Supplier $supplier)
    {
        $supplier->load(['purchases' => function ($query) {
            $query->orderBy('purchase_date', 'desc')->limit(20);
        }]);

        $purchaseSummary = Purchase::where('supplier_id', $supplier->id)
            ->selectRaw('
                COUNT(*) as total_purchases,
                SUM(grand_total) as total_amount,
                SUM(paid_amount) as total_paid,
                SUM(due_amount) as total_due
            ')
            ->first();

        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
            'summary' => $purchaseSummary,
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        try {
            $supplier->update($request->validated());

            return redirect()->route('suppliers.index')
                ->with('success', 'Supplier berhasil diperbarui');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui supplier: ' . $e->getMessage());
        }
    }

    public function destroy(Supplier $supplier)
    {
        try {
            // Check if supplier has purchases
            if ($supplier->purchases()->exists()) {
                return redirect()->back()
                    ->with('error', 'Supplier tidak dapat dihapus karena memiliki pembelian terkait');
            }

            $supplier->delete();

            return redirect()->route('suppliers.index')
                ->with('success', 'Supplier berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menghapus supplier: ' . $e->getMessage());
        }
    }
}
