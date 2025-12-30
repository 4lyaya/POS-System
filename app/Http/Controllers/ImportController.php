<?php

namespace App\Http\Controllers;

use App\Imports\ProductsImport;
use App\Imports\CustomersImport;
use App\Imports\SuppliersImport;
use App\Imports\PurchasesImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage-products');
    }

    // ==================== PRODUCT IMPORT ====================

    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
            'update_existing' => 'nullable|boolean',
            'skip_errors' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $import = new ProductsImport(
                $request->boolean('update_existing', false),
                $request->boolean('skip_errors', false)
            );

            Excel::import($import, $request->file('file'));

            DB::commit();

            $stats = $import->getImportStats();

            return redirect()->route('products.index')
                ->with('success', sprintf(
                    'Import berhasil! %d produk berhasil diimpor, %d gagal, %d diupdate.',
                    $stats['success'],
                    $stats['failed'],
                    $stats['updated']
                ))
                ->with('import_stats', $stats);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }

    public function importProductsForm()
    {
        $categories = Category::active()->get();
        $units = Unit::active()->get();

        return view('import.products', compact('categories', 'units'));
    }

    // ==================== CUSTOMER IMPORT ====================

    public function importCustomers(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
            'update_existing' => 'nullable|boolean',
            'skip_errors' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $import = new CustomersImport(
                $request->boolean('update_existing', false),
                $request->boolean('skip_errors', false)
            );

            Excel::import($import, $request->file('file'));

            DB::commit();

            $stats = $import->getImportStats();

            return redirect()->route('customers.index')
                ->with('success', sprintf(
                    'Import berhasil! %d pelanggan berhasil diimpor, %d gagal, %d diupdate.',
                    $stats['success'],
                    $stats['failed'],
                    $stats['updated']
                ));
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }

    // ==================== SUPPLIER IMPORT ====================

    public function importSuppliers(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
            'update_existing' => 'nullable|boolean',
            'skip_errors' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $import = new SuppliersImport(
                $request->boolean('update_existing', false),
                $request->boolean('skip_errors', false)
            );

            Excel::import($import, $request->file('file'));

            DB::commit();

            $stats = $import->getImportStats();

            return redirect()->route('suppliers.index')
                ->with('success', sprintf(
                    'Import berhasil! %d supplier berhasil diimpor, %d gagal, %d diupdate.',
                    $stats['success'],
                    $stats['failed'],
                    $stats['updated']
                ));
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }

    // ==================== PURCHASE IMPORT ====================

    public function importPurchases(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_date' => 'nullable|date',
            'skip_errors' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $import = new PurchasesImport(
                $request->supplier_id,
                $request->purchase_date ?: now()->toDateString(),
                $request->boolean('skip_errors', false)
            );

            Excel::import($import, $request->file('file'));

            DB::commit();

            $stats = $import->getImportStats();

            return redirect()->route('purchases.index')
                ->with('success', sprintf(
                    'Import berhasil! %d pembelian berhasil diimpor, %d item berhasil diproses, %d gagal.',
                    $stats['purchases_success'],
                    $stats['items_success'],
                    $stats['failed']
                ));
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }

    // ==================== QUICK IMPORT PRODUCTS (FROM JSON/ARRAY) ====================

    public function quickImportProducts(Request $request)
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.code' => 'required|string|max:50',
            'products.*.name' => 'required|string|max:255',
            'products.*.purchase_price' => 'required|numeric|min:0',
            'products.*.selling_price' => 'required|numeric|min:0|gt:products.*.purchase_price',
            'products.*.stock' => 'nullable|integer|min:0',
            'products.*.category_id' => 'nullable|exists:categories,id',
            'products.*.unit_id' => 'nullable|exists:units,id',
            'update_existing' => 'nullable|boolean',
        ]);

        $results = [
            'success' => 0,
            'failed' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($request->products as $index => $productData) {
                try {
                    // Check if product exists
                    $existingProduct = Product::where('code', $productData['code'])->first();

                    if ($existingProduct && $request->boolean('update_existing')) {
                        // Update existing product
                        $existingProduct->update($productData);
                        $results['updated']++;
                    } elseif (!$existingProduct) {
                        // Create new product
                        Product::create($productData);
                        $results['success']++;
                    } else {
                        // Skip existing product
                        $results['errors'][] = "Produk dengan kode {$productData['code']} sudah ada (baris {$index})";
                        $results['failed']++;
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Baris {$index}: " . $e->getMessage();
                }
            }

            DB::commit();

            $message = sprintf(
                'Import cepat berhasil! %d berhasil, %d gagal, %d diupdate.',
                $results['success'],
                $results['failed'],
                $results['updated']
            );

            if (!empty($results['errors'])) {
                $message .= ' Errors: ' . implode('; ', array_slice($results['errors'], 0, 5));
                if (count($results['errors']) > 5) {
                    $message .= ' dan ' . (count($results['errors']) - 5) . ' error lainnya.';
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage(),
            ], 422);
        }
    }

    // ==================== BULK STOCK UPDATE ====================

    public function bulkUpdateStock(Request $request)
    {
        $request->validate([
            'updates' => 'required|array',
            'updates.*.product_id' => 'required|exists:products,id',
            'updates.*.quantity' => 'required|integer',
            'updates.*.type' => 'required|in:set,increment,decrement',
            'updates.*.notes' => 'nullable|string|max:500',
            'adjustment_reason' => 'nullable|string|max:500',
        ]);

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($request->updates as $index => $update) {
                try {
                    $product = Product::findOrFail($update['product_id']);
                    $oldStock = $product->stock;

                    switch ($update['type']) {
                        case 'set':
                            $newStock = $update['quantity'];
                            break;
                        case 'increment':
                            $newStock = $oldStock + $update['quantity'];
                            break;
                        case 'decrement':
                            $newStock = $oldStock - $update['quantity'];
                            if ($newStock < 0) {
                                throw new \Exception("Stok tidak boleh negatif");
                            }
                            break;
                    }

                    $product->stock = $newStock;
                    $product->save();

                    // Record stock mutation
                    \App\Models\StockMutation::create([
                        'product_id' => $product->id,
                        'mutation_type' => 'adjustment',
                        'quantity' => abs($newStock - $oldStock),
                        'previous_stock' => $oldStock,
                        'current_stock' => $newStock,
                        'reference_type' => 'bulk_update',
                        'reference_id' => null,
                        'notes' => $update['notes'] ?? $request->adjustment_reason ?? 'Update stok massal',
                        'user_id' => auth()->id(),
                    ]);

                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Baris {$index}: " . $e->getMessage();
                }
            }

            DB::commit();

            $message = sprintf(
                'Update stok massal berhasil! %d berhasil, %d gagal.',
                $results['success'],
                $results['failed']
            );

            if (!empty($results['errors'])) {
                $message .= ' Errors: ' . implode('; ', array_slice($results['errors'], 0, 3));
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Update stok massal gagal: ' . $e->getMessage(),
            ], 422);
        }
    }

    // ==================== VALIDATE IMPORT FILE ====================

    public function validateImportFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
            'type' => 'required|in:products,customers,suppliers,purchases',
        ]);

        try {
            // Read first few rows
            $path = $request->file('file')->getRealPath();
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($path);
            $worksheet = $spreadsheet->getActiveSheet();

            $data = $worksheet->toArray();
            $headers = array_shift($data);

            // Validate headers based on type
            $requiredHeaders = [];
            switch ($request->type) {
                case 'products':
                    $requiredHeaders = ['code', 'name', 'purchase_price', 'selling_price'];
                    break;
                case 'customers':
                    $requiredHeaders = ['name'];
                    break;
                case 'suppliers':
                    $requiredHeaders = ['name'];
                    break;
                case 'purchases':
                    $requiredHeaders = ['product_code', 'quantity', 'unit_price'];
                    break;
            }

            $missingHeaders = array_diff($requiredHeaders, array_map('strtolower', $headers));

            if (!empty($missingHeaders)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Header yang diperlukan tidak ditemukan: ' . implode(', ', $missingHeaders),
                    'headers' => $headers,
                    'required_headers' => $requiredHeaders,
                ]);
            }

            // Validate first few rows
            $sampleRows = array_slice($data, 0, min(5, count($data)));
            $validationErrors = [];

            foreach ($sampleRows as $rowIndex => $row) {
                $rowData = array_combine($headers, $row);

                // Basic validation
                if ($request->type === 'products') {
                    if (empty($rowData['code']) || empty($rowData['name'])) {
                        $validationErrors[] = "Baris {$rowIndex}: Kode dan nama produk harus diisi";
                    }
                }
            }

            return response()->json([
                'valid' => empty($validationErrors),
                'message' => empty($validationErrors) ? 'File valid' : 'File memiliki error',
                'headers' => $headers,
                'sample_rows' => $sampleRows,
                'total_rows' => count($data),
                'errors' => $validationErrors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Error membaca file: ' . $e->getMessage(),
            ], 422);
        }
    }
}
