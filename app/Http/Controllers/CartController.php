<?php

namespace App\Http\Controllers;

use App\Services\POS\CartService;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
        $this->middleware('permission:manage-sales');
    }

    // ==================== CART MANAGEMENT ====================

    public function index()
    {
        $cartItems = $this->cartService->getCartItems();
        $cartSummary = $this->cartService->getCartSummary();

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

        return view('pos.cart', compact('cartItems', 'cartSummary', 'products', 'customers'));
    }

    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $item = $this->cartService->addItem($request->product_id, $request->quantity);

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil ditambahkan ke keranjang',
                'item' => $item,
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

    public function updateItem(Request $request, $productId): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        try {
            if ($request->quantity == 0) {
                $this->cartService->removeItem($productId);
                $message = 'Produk berhasil dihapus dari keranjang';
            } else {
                $this->cartService->updateItem($productId, $request->quantity);
                $message = 'Quantity berhasil diperbarui';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
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

    public function removeItem($productId): JsonResponse
    {
        try {
            $this->cartService->removeItem($productId);

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dihapus dari keranjang',
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

    public function clearCart(): JsonResponse
    {
        $this->cartService->clearCart();

        return response()->json([
            'success' => true,
            'message' => 'Keranjang berhasil dikosongkan',
            'cart' => [],
            'summary' => [
                'total_items' => 0,
                'subtotal' => 0,
                'tax' => 0,
                'discount' => 0,
                'grand_total' => 0,
            ],
        ]);
    }

    public function getCart(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'cart' => $this->cartService->getCartItems(),
            'summary' => $this->cartService->getCartSummary(),
        ]);
    }

    // ==================== CART CALCULATIONS ====================

    public function calculateTotals(Request $request): JsonResponse
    {
        $request->validate([
            'discount_type' => 'nullable|in:percent,amount',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'service_charge' => 'nullable|numeric|min:0',
        ]);

        $cartSummary = $this->cartService->getCartSummary();
        $subtotal = $cartSummary['subtotal'];

        // Calculate discount
        $discount = 0;
        if ($request->has('discount_value')) {
            if ($request->discount_type === 'percent') {
                $discount = $subtotal * ($request->discount_value / 100);
            } else {
                $discount = min($request->discount_value, $subtotal);
            }
        }

        // Calculate tax
        $taxPercentage = $request->tax_percentage ?? 11; // Default 11% PPN
        $taxableAmount = $subtotal - $discount;
        $tax = $taxableAmount * ($taxPercentage / 100);

        // Calculate service charge
        $serviceCharge = $request->service_charge ?? 0;

        // Calculate grand total
        $grandTotal = $subtotal - $discount + $tax + $serviceCharge;

        return response()->json([
            'success' => true,
            'calculations' => [
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax_percentage' => $taxPercentage,
                'tax' => $tax,
                'service_charge' => $serviceCharge,
                'grand_total' => $grandTotal,
            ],
            'formatted' => [
                'subtotal' => 'Rp ' . number_format($subtotal, 0, ',', '.'),
                'discount' => 'Rp ' . number_format($discount, 0, ',', '.'),
                'tax' => 'Rp ' . number_format($tax, 0, ',', '.'),
                'service_charge' => 'Rp ' . number_format($serviceCharge, 0, ',', '.'),
                'grand_total' => 'Rp ' . number_format($grandTotal, 0, ',', '.'),
            ],
        ]);
    }

    public function calculateChange(Request $request): JsonResponse
    {
        $request->validate([
            'grand_total' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
        ]);

        $change = $request->paid_amount - $request->grand_total;

        return response()->json([
            'success' => true,
            'change' => max(0, $change),
            'formatted' => 'Rp ' . number_format(max(0, $change), 0, ',', '.'),
            'is_sufficient' => $change >= 0,
        ]);
    }

    // ==================== CART VALIDATION ====================

    public function validateCart(): JsonResponse
    {
        $errors = $this->cartService->validateStockAvailability();

        if (empty($errors)) {
            return response()->json([
                'success' => true,
                'message' => 'Stok semua produk mencukupi',
                'is_valid' => true,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Validasi stok gagal',
                'is_valid' => false,
                'errors' => $errors,
            ]);
        }
    }

    public function checkStock($productId): JsonResponse
    {
        try {
            $product = Product::findOrFail($productId);
            $cart = $this->cartService->getCart();

            $requestedQuantity = $cart[$productId]['quantity'] ?? 0;
            $availableStock = $product->stock;

            return response()->json([
                'success' => true,
                'product_id' => $productId,
                'product_name' => $product->name,
                'requested_quantity' => $requestedQuantity,
                'available_stock' => $availableStock,
                'is_available' => $availableStock >= $requestedQuantity,
                'shortage' => max(0, $requestedQuantity - $availableStock),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ==================== CUSTOMER MANAGEMENT IN CART ====================

    public function setCustomer(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        try {
            $customer = null;

            if ($request->filled('customer_id')) {
                $customer = Customer::find($request->customer_id);
            } elseif ($request->filled('customer_name')) {
                // Create or find customer by phone
                if ($request->filled('customer_phone')) {
                    $customer = Customer::firstOrCreate(
                        ['phone' => $request->customer_phone],
                        ['name' => $request->customer_name]
                    );
                } else {
                    // Create temporary customer without phone
                    $customer = Customer::create([
                        'name' => $request->customer_name,
                        'phone' => null,
                        'is_active' => true,
                    ]);
                }
            }

            // Store customer in session
            session()->put('pos_customer', $customer ? $customer->id : null);

            return response()->json([
                'success' => true,
                'message' => $customer ? 'Pelanggan berhasil ditetapkan' : 'Pelanggan dihapus',
                'customer' => $customer,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menetapkan pelanggan: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function getCustomer(): JsonResponse
    {
        $customerId = session()->get('pos_customer');
        $customer = $customerId ? Customer::find($customerId) : null;

        return response()->json([
            'success' => true,
            'customer' => $customer,
        ]);
    }

    // ==================== CART PRESETS & TEMPLATES ====================

    public function saveCartPreset(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $cart = $this->cartService->getCart();

            if (empty($cart)) {
                throw new \Exception('Keranjang kosong, tidak bisa menyimpan preset');
            }

            $presetData = [
                'name' => $request->name,
                'description' => $request->description,
                'items' => $cart,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ];

            // Save to database or session
            $presets = session()->get('cart_presets', []);
            $presets[] = $presetData;
            session()->put('cart_presets', $presets);

            return response()->json([
                'success' => true,
                'message' => 'Preset keranjang berhasil disimpan',
                'preset' => $presetData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan preset: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function loadCartPreset($index): JsonResponse
    {
        try {
            $presets = session()->get('cart_presets', []);

            if (!isset($presets[$index])) {
                throw new \Exception('Preset tidak ditemukan');
            }

            $preset = $presets[$index];

            // Clear current cart
            $this->cartService->clearCart();

            // Load preset items
            foreach ($preset['items'] as $productId => $item) {
                $this->cartService->addItem($productId, $item['quantity']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Preset berhasil dimuat',
                'cart' => $this->cartService->getCartItems(),
                'summary' => $this->cartService->getCartSummary(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat preset: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function getCartPresets(): JsonResponse
    {
        $presets = session()->get('cart_presets', []);

        return response()->json([
            'success' => true,
            'presets' => $presets,
        ]);
    }

    // ==================== QUICK ACTIONS ====================

    public function applyDiscount(Request $request): JsonResponse
    {
        $request->validate([
            'discount_type' => 'required|in:percent,amount',
            'discount_value' => 'required|numeric|min:0',
        ]);

        session()->put('pos_discount', [
            'type' => $request->discount_type,
            'value' => $request->discount_value,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Diskon berhasil diterapkan',
            'discount' => [
                'type' => $request->discount_type,
                'value' => $request->discount_value,
            ],
        ]);
    }

    public function removeDiscount(): JsonResponse
    {
        session()->forget('pos_discount');

        return response()->json([
            'success' => true,
            'message' => 'Diskon berhasil dihapus',
        ]);
    }

    public function setPaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|in:cash,transfer,qris,debit,credit',
        ]);

        session()->put('pos_payment_method', $request->payment_method);

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil ditetapkan',
            'payment_method' => $request->payment_method,
        ]);
    }

    // ==================== CART SUMMARY & STATS ====================

    public function getCartStats(): JsonResponse
    {
        $cartItems = $this->cartService->getCartItems();
        $cartSummary = $this->cartService->getCartSummary();

        $stats = [
            'total_items' => count($cartItems),
            'total_quantity' => $cartSummary['total_items'],
            'total_products' => count(array_unique(array_column($cartItems, 'id'))),
            'subtotal' => $cartSummary['subtotal'],
            'estimated_tax' => $cartSummary['subtotal'] * 0.11, // Default 11%
            'estimated_total' => $cartSummary['subtotal'] * 1.11,
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'formatted_stats' => [
                'total_items' => number_format($stats['total_items'], 0, ',', '.'),
                'total_quantity' => number_format($stats['total_quantity'], 0, ',', '.'),
                'total_products' => number_format($stats['total_products'], 0, ',', '.'),
                'subtotal' => 'Rp ' . number_format($stats['subtotal'], 0, ',', '.'),
                'estimated_tax' => 'Rp ' . number_format($stats['estimated_tax'], 0, ',', '.'),
                'estimated_total' => 'Rp ' . number_format($stats['estimated_total'], 0, ',', '.'),
            ],
        ]);
    }

    // ==================== BULK CART OPERATIONS ====================

    public function bulkUpdateCart(Request $request): JsonResponse
    {
        $request->validate([
            'updates' => 'required|array',
            'updates.*.product_id' => 'required|exists:products,id',
            'updates.*.quantity' => 'required|integer|min:0',
        ]);

        try {
            $results = [
                'success' => 0,
                'failed' => 0,
                'errors' => [],
            ];

            foreach ($request->updates as $update) {
                try {
                    if ($update['quantity'] == 0) {
                        $this->cartService->removeItem($update['product_id']);
                    } else {
                        $this->cartService->updateItem($update['product_id'], $update['quantity']);
                    }
                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Produk ID {$update['product_id']}: " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => sprintf('Update massal berhasil! %d berhasil, %d gagal.', $results['success'], $results['failed']),
                'results' => $results,
                'cart' => $this->cartService->getCartItems(),
                'summary' => $this->cartService->getCartSummary(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update massal gagal: ' . $e->getMessage(),
            ], 422);
        }
    }

    // ==================== CART EXPORT (FOR PRINTING/SAVING) ====================

    public function exportCart(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'required|in:json,pdf',
        ]);

        try {
            $cartItems = $this->cartService->getCartItems();
            $cartSummary = $this->cartService->getCartSummary();

            $data = [
                'cart' => $cartItems,
                'summary' => $cartSummary,
                'generated_at' => now()->toDateTimeString(),
                'generated_by' => auth()->user()->name,
            ];

            if ($request->format === 'json') {
                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'download_url' => null, // You can implement file download if needed
                ]);
            } else {
                // PDF export logic here
                return response()->json([
                    'success' => false,
                    'message' => 'PDF export untuk keranjang belum tersedia',
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ekspor gagal: ' . $e->getMessage(),
            ], 422);
        }
    }
}
