<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sale\StoreSaleRequest;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Customer;
use App\Services\POS\SaleService;
use App\Services\POS\CartService;
use App\Pdf\ReceiptPdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SaleController extends Controller
{
    protected $saleService;
    protected $cartService;

    public function __construct(SaleService $saleService, CartService $cartService)
    {
        $this->saleService = $saleService;
        $this->cartService = $cartService;
        $this->middleware('permission:manage-sales');
    }

    public function index(Request $request)
    {
        $query = Sale::with(['customer', 'user']);

        // Date range filter
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('sale_date', [$request->start_date, $request->end_date]);
        } else {
            // Default to today
            $query->whereDate('sale_date', today());
        }

        // Search by invoice number or customer
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by cashier
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $sales = $query->orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => $request->only(['search', 'start_date', 'end_date', 'payment_method', 'user_id']),
        ]);
    }

    public function create()
    {
        $products = Product::active()
            ->where('stock', '>', 0)
            ->with(['category', 'unit'])
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'price' => $product->selling_price,
                    'stock' => $product->stock,
                    'image' => $product->image_url,
                    'category' => $product->category?->name,
                    'unit' => $product->unit?->short_name,
                ];
            });

        $customers = Customer::active()->get();

        return Inertia::render('POS/Index', [
            'products' => $products,
            'customers' => $customers,
            'cart' => $this->cartService->getCartItems(),
            'cart_summary' => $this->cartService->getCartSummary(),
        ]);
    }

    public function store(StoreSaleRequest $request)
    {
        try {
            $sale = $this->saleService->processSale($request->validated());

            // Clear cart after successful sale
            $this->cartService->clearCart();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil diproses',
                'sale' => $sale->load(['customer', 'items.product']),
                'receipt_url' => route('sales.receipt', $sale),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses transaksi: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function show(Sale $sale)
    {
        $sale->load(['customer', 'user', 'items.product']);

        return Inertia::render('Sales/Show', [
            'sale' => $sale,
        ]);
    }

    public function receipt(Sale $sale)
    {
        $sale->load(['customer', 'user', 'items.product']);
        $pdf = new ReceiptPdf($sale);

        return response($pdf->generate(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="struk-' . $sale->invoice_number . '.pdf"',
        ]);
    }

    public function downloadReceipt(Sale $sale)
    {
        $sale->load(['customer', 'user', 'items.product']);
        $pdf = new ReceiptPdf($sale);
        $pdf->download();
    }

    public function cancel(Request $request, Sale $sale)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            if ($sale->payment_status === 'cancelled') {
                return redirect()->back()
                    ->with('error', 'Transaksi sudah dibatalkan sebelumnya');
            }

            $this->saleService->cancelSale($sale, $request->reason);

            return redirect()->route('sales.index')
                ->with('success', 'Transaksi berhasil dibatalkan');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal membatalkan transaksi: ' . $e->getMessage());
        }
    }

    // Cart management methods
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $item = $this->cartService->addItem($request->product_id, $request->quantity);

            return response()->json([
                'success' => true,
                'cart' => $this->cartService->getCartItems(),
                'summary' => $this->cartService->getCartSummary(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function updateCart(Request $request, $productId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        try {
            if ($request->quantity == 0) {
                $this->cartService->removeItem($productId);
            } else {
                $this->cartService->updateItem($productId, $request->quantity);
            }

            return response()->json([
                'success' => true,
                'cart' => $this->cartService->getCartItems(),
                'summary' => $this->cartService->getCartSummary(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function clearCart()
    {
        $this->cartService->clearCart();

        return response()->json([
            'success' => true,
            'message' => 'Keranjang berhasil dikosongkan',
        ]);
    }

    public function getCart()
    {
        return response()->json([
            'cart' => $this->cartService->getCartItems(),
            'summary' => $this->cartService->getCartSummary(),
        ]);
    }
}
