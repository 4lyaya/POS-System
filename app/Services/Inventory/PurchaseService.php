<?php

namespace App\Services\Inventory;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\StockMutation;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PurchaseService extends BaseService
{
    public function processPurchase(array $data): Purchase
    {
        return $this->executeInTransaction(function () use ($data) {
            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber();

            // Handle supplier
            $supplier = null;
            if (!empty($data['supplier_id'])) {
                $supplier = Supplier::find($data['supplier_id']);
            }

            // Calculate totals
            $subtotal = 0;
            $itemsCount = 0;

            foreach ($data['items'] as $item) {
                $subtotal += ($item['unit_price'] * $item['quantity']);
                $itemsCount += $item['quantity'];
            }

            $tax = $data['tax'] ?? 0;
            $discount = $data['discount'] ?? 0;
            $shippingCost = $data['shipping_cost'] ?? 0;
            $grandTotal = $subtotal + $tax + $shippingCost - $discount;

            // Create purchase
            $purchase = Purchase::create([
                'invoice_number' => $invoiceNumber,
                'supplier_id' => $supplier?->id,
                'user_id' => Auth::id(),
                'purchase_date' => $data['purchase_date'] ?? now(),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'shipping_cost' => $shippingCost,
                'grand_total' => $grandTotal,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'payment_status' => $data['payment_status'] ?? ($data['payment_method'] === 'cash' ? 'paid' : 'unpaid'),
                'paid_amount' => $data['paid_amount'] ?? ($data['payment_method'] === 'cash' ? $grandTotal : 0),
                'due_amount' => $grandTotal - ($data['paid_amount'] ?? 0),
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Process purchase items
            $this->processPurchaseItems($purchase, $data['items']);

            // Update supplier balance
            if ($supplier && $data['payment_status'] !== 'paid') {
                $supplier->balance += $purchase->due_amount;
                $supplier->save();
            }

            return $purchase;
        }, 'Gagal memproses pembelian');
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'PUR';
        $datePart = now()->format('Ymd');

        $lastPurchase = Purchase::whereDate('created_at', today())
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

    private function processPurchaseItems(Purchase $purchase, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);

            // Create purchase item
            $purchaseItem = PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['quantity'] * $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'batch_number' => $item['batch_number'] ?? null,
                'expired_date' => $item['expired_date'] ?? null,
            ]);

            // Update product stock and purchase price
            $oldStock = $product->stock;
            $product->increment('stock', $item['quantity']);

            // Update purchase price if needed (average or latest)
            if (empty($product->purchase_price) || $data['update_price'] ?? false) {
                $product->purchase_price = $item['unit_price'];
                $product->save();
            }

            // Record stock mutation
            StockMutation::create([
                'product_id' => $product->id,
                'mutation_type' => 'in',
                'quantity' => $item['quantity'],
                'previous_stock' => $oldStock,
                'current_stock' => $product->stock,
                'reference_type' => Purchase::class,
                'reference_id' => $purchase->id,
                'notes' => 'Pembelian #' . $purchase->invoice_number,
                'user_id' => Auth::id(),
            ]);
        }
    }

    public function updatePayment(Purchase $purchase, array $paymentData): Purchase
    {
        return $this->executeInTransaction(function () use ($purchase, $paymentData) {
            $paymentAmount = $paymentData['amount'];

            if ($paymentAmount > $purchase->due_amount) {
                throw new \Exception('Jumlah pembayaran melebihi hutang');
            }

            $purchase->paid_amount += $paymentAmount;
            $purchase->due_amount -= $paymentAmount;

            if ($purchase->due_amount <= 0) {
                $purchase->payment_status = 'paid';
            } else {
                $purchase->payment_status = 'partial';
            }

            $purchase->save();

            // Update supplier balance
            if ($purchase->supplier) {
                $purchase->supplier->balance -= $paymentAmount;
                $purchase->supplier->save();
            }

            return $purchase->fresh();
        }, 'Gagal memperbarui pembayaran');
    }

    public function getPurchaseSummary($startDate, $endDate)
    {
        return Purchase::whereBetween('purchase_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_purchases,
                SUM(grand_total) as total_amount,
                SUM(paid_amount) as total_paid,
                SUM(due_amount) as total_due,
                supplier_id
            ')
            ->with('supplier')
            ->groupBy('supplier_id')
            ->get();
    }
}
