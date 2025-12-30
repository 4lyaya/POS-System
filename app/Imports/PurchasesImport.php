<?php

namespace App\Imports;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\StockMutation;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PurchasesImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading, SkipsOnError, SkipsOnFailure
{
    use SkipsErrors, SkipsFailures;

    private $supplierId;
    private $purchaseDate;
    private $skipErrors;
    private $currentPurchase;
    private $products;
    private $suppliers;
    private $importStats;

    public function __construct($supplierId = null, $purchaseDate = null, $skipErrors = false)
    {
        $this->supplierId = $supplierId;
        $this->purchaseDate = $purchaseDate ? Carbon::parse($purchaseDate) : now();
        $this->skipErrors = $skipErrors;
        $this->products = Product::all()->keyBy('code');
        $this->suppliers = Supplier::all()->keyBy('name');
        $this->importStats = [
            'purchases_success' => 0,
            'items_success' => 0,
            'failed' => 0,
            'errors' => [],
        ];
    }

    public function model(array $row)
    {
        DB::beginTransaction();

        try {
            // Handle supplier from row if not provided in constructor
            $supplierId = $this->supplierId;
            $supplierName = $row['supplier'] ?? $row['supplier_name'] ?? null;

            if (!$supplierId && $supplierName) {
                if ($this->suppliers->has($supplierName)) {
                    $supplierId = $this->suppliers[$supplierName]->id;
                } else {
                    // Create new supplier
                    $supplier = Supplier::create([
                        'name' => $supplierName,
                        'code' => 'SUP' . Str::random(5),
                        'is_active' => true,
                    ]);
                    $this->suppliers->put($supplierName, $supplier);
                    $supplierId = $supplier->id;
                }
            }

            // Create purchase if not exists
            if (!$this->currentPurchase) {
                $this->currentPurchase = Purchase::create([
                    'invoice_number' => $this->generateInvoiceNumber(),
                    'supplier_id' => $supplierId,
                    'user_id' => auth()->id(),
                    'purchase_date' => $this->purchaseDate,
                    'subtotal' => 0,
                    'tax' => 0,
                    'discount' => 0,
                    'shipping_cost' => $row['shipping_cost'] ?? 0,
                    'grand_total' => 0,
                    'payment_method' => $row['payment_method'] ?? 'cash',
                    'payment_status' => $row['payment_status'] ?? 'unpaid',
                    'paid_amount' => $row['paid_amount'] ?? 0,
                    'due_amount' => 0,
                    'due_date' => isset($row['due_date']) ? Carbon::parse($row['due_date']) : null,
                    'notes' => $row['notes'] ?? null,
                ]);

                $this->importStats['purchases_success']++;
            }

            // Find product
            $productCode = $row['product_code'] ?? $row['code'] ?? null;
            $productBarcode = $row['barcode'] ?? null;
            $productName = $row['product_name'] ?? $row['name'] ?? null;

            $product = null;

            if ($productCode && isset($this->products[$productCode])) {
                $product = $this->products[$productCode];
            } elseif ($productBarcode) {
                $product = Product::where('barcode', $productBarcode)->first();
                if ($product) {
                    $this->products->put($product->code, $product);
                }
            }

            // If product not found by code/barcode, try to find by name
            if (!$product && $productName) {
                $product = Product::where('name', 'like', "%{$productName}%")->first();
                if ($product) {
                    $this->products->put($product->code, $product);
                }
            }

            if (!$product) {
                throw new \Exception("Produk tidak ditemukan: {$productCode} / {$productName}");
            }

            // Validate required fields
            $quantity = $row['quantity'] ?? $row['qty'] ?? 1;
            $unitPrice = $row['unit_price'] ?? $row['price'] ?? $row['harga'] ?? 0;

            if ($quantity <= 0) {
                throw new \Exception("Quantity harus lebih dari 0");
            }

            if ($unitPrice <= 0) {
                throw new \Exception("Harga unit harus lebih dari 0");
            }

            // Calculate item total
            $discount = $row['discount'] ?? 0;
            $totalPrice = ($quantity * $unitPrice) - $discount;

            // Create purchase item
            $purchaseItem = PurchaseItem::create([
                'purchase_id' => $this->currentPurchase->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'discount' => $discount,
                'batch_number' => $row['batch_number'] ?? null,
                'expired_date' => isset($row['expired_date']) ? Carbon::parse($row['expired_date']) : null,
            ]);

            // Update product stock
            $oldStock = $product->stock;
            $product->increment('stock', $quantity);

            // Update product purchase price if needed
            $updatePrice = $row['update_price'] ?? false;
            if ($updatePrice) {
                $product->purchase_price = $unitPrice;
                $product->save();
            }

            // Record stock mutation
            StockMutation::create([
                'product_id' => $product->id,
                'mutation_type' => 'in',
                'quantity' => $quantity,
                'previous_stock' => $oldStock,
                'current_stock' => $product->stock,
                'reference_type' => Purchase::class,
                'reference_id' => $this->currentPurchase->id,
                'notes' => 'Import pembelian: ' . $this->currentPurchase->invoice_number,
                'user_id' => auth()->id(),
            ]);

            // Update purchase totals
            $this->currentPurchase->subtotal += $totalPrice;
            $this->currentPurchase->grand_total = $this->currentPurchase->subtotal +
                $this->currentPurchase->tax +
                $this->currentPurchase->shipping_cost -
                $this->currentPurchase->discount;
            $this->currentPurchase->due_amount = $this->currentPurchase->grand_total -
                $this->currentPurchase->paid_amount;
            $this->currentPurchase->save();

            $this->importStats['items_success']++;

            DB::commit();

            return $purchaseItem;
        } catch (\Exception $e) {
            DB::rollBack();

            $this->importStats['failed']++;
            $this->importStats['errors'][] = "Baris: " . json_encode($row) . " - Error: " . $e->getMessage();

            if ($this->skipErrors) {
                return null;
            }

            throw $e;
        }
    }

    public function rules(): array
    {
        return [
            'product_code' => 'required_without_all:product_name,barcode',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'product_code.required_without_all' => 'Kode produk, nama produk, atau barcode harus diisi',
            'quantity.required' => 'Quantity harus diisi',
            'quantity.min' => 'Quantity minimal 0.01',
            'unit_price.required' => 'Harga unit harus diisi',
            'unit_price.min' => 'Harga unit minimal 0',
        ];
    }

    public function batchSize(): int
    {
        return 50;
    }

    public function chunkSize(): int
    {
        return 50;
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'PUR';
        $datePart = $this->purchaseDate->format('Ymd');

        $lastPurchase = Purchase::whereDate('created_at', $this->purchaseDate)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastPurchase && strpos($lastPurchase->invoice_number, $prefix . '-' . $datePart) === 0) {
            $lastNumber = intval(substr($lastPurchase->invoice_number, -4));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . '-' . $datePart . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function getImportStats(): array
    {
        return $this->importStats;
    }

    public function onRow($row)
    {
        // Check if this row indicates a new purchase
        $hasPurchaseHeader = isset($row['invoice_number']) ||
            (isset($row['supplier']) && !empty($row['supplier'])) ||
            (isset($row['supplier_name']) && !empty($row['supplier_name']));

        if ($hasPurchaseHeader && $this->currentPurchase) {
            // Reset current purchase for new purchase in the file
            $this->currentPurchase = null;
        }
    }
}
