<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Exports\ProductsExport;
use App\Imports\ProductsImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Inventory\ProductService;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\ImportProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
        $this->middleware('permission:manage-products')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'unit']);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

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

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $products = $query->orderBy('name')->paginate(20);
        $categories = Category::active()->get();
        $units = Unit::active()->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'units' => $units,
            'filters' => $request->only(['search', 'category_id', 'stock_status', 'is_active']),
        ]);
    }

    public function create()
    {
        $categories = Category::active()->get();
        $units = Unit::active()->get();

        return Inertia::render('Products/Create', [
            'categories' => $categories,
            'units' => $units,
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        try {
            $product = $this->productService->createProduct($request->validated());

            return redirect()->route('products.index')
                ->with('success', 'Produk berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal menambahkan produk: ' . $e->getMessage());
        }
    }

    public function show(Product $product)
    {
        $product->load(['category', 'unit', 'stockMutations' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(20);
        }]);

        return Inertia::render('Products/Show', [
            'product' => $product,
            'stock_history' => $product->stockMutations,
        ]);
    }

    public function edit(Product $product)
    {
        $categories = Category::active()->get();
        $units = Unit::active()->get();

        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'units' => $units,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        try {
            $product = $this->productService->updateProduct($product, $request->validated());

            return redirect()->route('products.index')
                ->with('success', 'Produk berhasil diperbarui');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui produk: ' . $e->getMessage());
        }
    }

    public function destroy(Product $product)
    {
        try {
            // Check if product has transactions
            if ($product->saleItems()->exists() || $product->purchaseItems()->exists()) {
                return redirect()->back()
                    ->with('error', 'Produk tidak dapat dihapus karena memiliki transaksi terkait');
            }

            $product->delete();

            return redirect()->route('products.index')
                ->with('success', 'Produk berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menghapus produk: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $categoryId = $request->category_id;
        $stockStatus = $request->stock_status;

        $filename = 'products-' . date('Y-m-d-H-i') . '.xlsx';

        return Excel::download(new ProductsExport($categoryId, $stockStatus), $filename);
    }

    public function import(ImportProductRequest $request)
    {
        try {
            Excel::import(new ProductsImport, $request->file('file'));

            return redirect()->route('products.index')
                ->with('success', 'Produk berhasil diimpor');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal mengimpor produk: ' . $e->getMessage());
        }
    }

    public function adjustStock(Request $request, Product $product)
    {
        $request->validate([
            'adjustment_type' => 'required|in:in,out,set',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $data = [
                'new_stock' => $request->adjustment_type === 'set'
                    ? $request->quantity
                    : ($product->stock + ($request->adjustment_type === 'in' ? $request->quantity : -$request->quantity)),
                'notes' => $request->notes,
            ];

            $this->productService->adjustStock($product, $data);

            return redirect()->back()
                ->with('success', 'Stok berhasil disesuaikan');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menyesuaikan stok: ' . $e->getMessage());
        }
    }
}
