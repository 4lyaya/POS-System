<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage-purchases');
    }

    public function index(Request $request)
    {
        $query = Expense::with('user');

        // Date range filter
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('expense_date', [$request->start_date, $request->end_date]);
        } else {
            // Default to this month
            $query->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $expenses = $query->orderBy('expense_date', 'desc')->paginate(20);

        // Get unique categories for filter
        $categories = Expense::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'filters' => $request->only(['search', 'start_date', 'end_date', 'category']),
        ]);
    }

    public function create()
    {
        $categories = Expense::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('Expenses/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreExpenseRequest $request)
    {
        try {
            // Generate expense number
            $expenseNumber = $this->generateExpenseNumber();

            $expense = Expense::create(array_merge($request->validated(), [
                'expense_number' => $expenseNumber,
                'user_id' => auth()->id(),
            ]));

            return redirect()->route('expenses.index')
                ->with('success', 'Pengeluaran berhasil dicatat');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal mencatat pengeluaran: ' . $e->getMessage());
        }
    }

    public function show(Expense $expense)
    {
        $expense->load('user');

        return Inertia::render('Expenses/Show', [
            'expense' => $expense,
        ]);
    }

    public function edit(Expense $expense)
    {
        $categories = Expense::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('Expenses/Edit', [
            'expense' => $expense,
            'categories' => $categories,
        ]);
    }

    public function update(StoreExpenseRequest $request, Expense $expense)
    {
        try {
            $expense->update($request->validated());

            return redirect()->route('expenses.index')
                ->with('success', 'Pengeluaran berhasil diperbarui');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui pengeluaran: ' . $e->getMessage());
        }
    }

    public function destroy(Expense $expense)
    {
        try {
            $expense->delete();

            return redirect()->route('expenses.index')
                ->with('success', 'Pengeluaran berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menghapus pengeluaran: ' . $e->getMessage());
        }
    }

    public function getExpenseSummary(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $summary = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->selectRaw('
                category,
                COUNT(*) as count,
                SUM(amount) as total_amount
            ')
            ->groupBy('category')
            ->orderBy('total_amount', 'desc')
            ->get();

        $total = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->selectRaw('COUNT(*) as total_count, SUM(amount) as total_amount')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'total' => $total,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ],
        ]);
    }

    private function generateExpenseNumber()
    {
        $prefix = 'EXP';
        $datePart = now()->format('Ymd');

        $lastExpense = Expense::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastExpense && strpos($lastExpense->expense_number, $prefix . '-' . $datePart) === 0) {
            $lastNumber = intval(substr($lastExpense->expense_number, -4));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . '-' . $datePart . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
