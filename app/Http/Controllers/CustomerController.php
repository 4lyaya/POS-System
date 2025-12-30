<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sale\StoreCustomerRequest;
use App\Http\Requests\Sale\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage-customers');
    }

    public function index(Request $request)
    {
        $customers = Customer::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('member_id', 'like', "%{$search}%");
            })
            ->when($request->has_points, function ($query) {
                $query->where('points', '>', 0);
            })
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search', 'has_points']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Customers/Create');
    }

    public function store(StoreCustomerRequest $request)
    {
        try {
            Customer::create($request->validated());

            return redirect()->route('customers.index')
                ->with('success', 'Pelanggan berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal menambahkan pelanggan: ' . $e->getMessage());
        }
    }

    public function show(Customer $customer)
    {
        $customer->load(['sales' => function ($query) {
            $query->orderBy('sale_date', 'desc')->limit(20);
        }]);

        $salesSummary = Sale::where('customer_id', $customer->id)
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(grand_total) as total_spent,
                AVG(grand_total) as average_spent,
                MAX(sale_date) as last_transaction
            ')
            ->first();

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'summary' => $salesSummary,
        ]);
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        try {
            $customer->update($request->validated());

            return redirect()->route('customers.index')
                ->with('success', 'Pelanggan berhasil diperbarui');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui pelanggan: ' . $e->getMessage());
        }
    }

    public function destroy(Customer $customer)
    {
        try {
            // Check if customer has sales
            if ($customer->sales()->exists()) {
                return redirect()->back()
                    ->with('error', 'Pelanggan tidak dapat dihapus karena memiliki transaksi terkait');
            }

            $customer->delete();

            return redirect()->route('customers.index')
                ->with('success', 'Pelanggan berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menghapus pelanggan: ' . $e->getMessage());
        }
    }
}
