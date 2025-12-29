<?php

namespace App\Services\POS;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\Customer;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SaleService extends BaseService
{
    public function processSale(array $data): Sale
    {
        return $this->executeInTransaction(function () use ($data) {
            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber();

            // Handle customer
            $customer = null;
            if (!empty($data['customer_id'])) {
                $customer = Customer::find($data['customer_id']);
            } elseif (!empty($data['customer_name'])) {
                $customer = Customer::firstOrCreate(
                    ['phone' => $data['customer_phone'] ?? null],
                    ['name' => $data['customer_name']]
                );
            }

            // Create sale
            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customer?->id,
                'user_id' => Auth::id(),
                'sale_date' => now(),
                'items_count' => count($data['items']),
                'subtotal' => $data['subtotal'],
                'tax' => $data['tax'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'service_charge' => $data['service_charge'] ?? 0,
                'grand_total' => $data['grand_total'],
                'payment_method' => $data['payment_method'],
                'payment_status' => 'paid',
                'paid_amount' => $data['paid_amount'],
                'change_amount' => $data['change_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            // Process sale items
            $this->processSaleItems($sale, $data['items']);

            // Update customer stats if exists
            if ($customer) {
                $this->updateCustomerStats($customer, $sale);
            }

            return $sale;
        }, 'Gagal memproses transaksi penjualan');
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $datePart = now()->format('Ymd');

        $lastSale = Sale::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastSale && strpos($lastSale->invoice_number, $prefix . '-' . $datePart) === 0) {
            $lastNumber = intval(substr($lastSale->invoice_number, -4));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . '-' . $datePart . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function processSaleItems(Sale $sale, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);

            // Validate stock
            if ($product->stock < $item['quantity']) {
                throw new \Exception("Stok produk {$product->name} tidak mencukupi. Stok tersedia: {$product->stock}");
            }

            // Create sale item
            $saleItem = SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
                'total_price' => $item['quantity'] * $item['price'],
                'discount' => $item['discount'] ?? 0,
            ]);

            // Update product stock
            $oldStock = $product->stock;
            $product->decrement('stock', $item['quantity']);

            // Record stock mutation
            StockMutation::create([
                'product_id' => $product->id,
                'mutation_type' => 'out',
                'quantity' => $item['quantity'],
                'previous_stock' => $oldStock,
                'current_stock' => $product->stock,
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
                'notes' => 'Penjualan #' . $sale->invoice_number,
                'user_id' => Auth::id(),
            ]);
        }
    }

    private function updateCustomerStats(Customer $customer, Sale $sale): void
    {
        $customer->total_purchases += $sale->grand_total;
        $customer->last_purchase = now();

        // Add points (1 point per 10,000 purchase)
        $points = floor($sale->grand_total / 10000);
        if ($points > 0) {
            $customer->points += $points;
        }

        $customer->save();
    }

    public function getTodaySales()
    {
        return Sale::with(['customer', 'user', 'items.product'])
            ->whereDate('sale_date', today())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getSalesSummary($startDate, $endDate)
    {
        return Sale::whereBetween('sale_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(grand_total) as total_sales,
                SUM(paid_amount) as total_paid,
                AVG(grand_total) as average_transaction,
                SUM(items_count) as total_items_sold,
                payment_method
            ')
            ->groupBy('payment_method')
            ->get();
    }

    public function cancelSale(Sale $sale, string $reason): bool
    {
        return $this->executeInTransaction(function () use ($sale, $reason) {
            // Return stock for each item
            foreach ($sale->items as $item) {
                $product = $item->product;
                $oldStock = $product->stock;
                $product->increment('stock', $item->quantity);

                // Record stock mutation for cancellation
                StockMutation::create([
                    'product_id' => $product->id,
                    'mutation_type' => 'in',
                    'quantity' => $item->quantity,
                    'previous_stock' => $oldStock,
                    'current_stock' => $product->stock,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'notes' => 'Pembatalan penjualan: ' . $reason,
                    'user_id' => Auth::id(),
                ]);
            }

            // Update sale status
            $sale->update([
                'payment_status' => 'cancelled',
                'metadata' => array_merge($sale->metadata ?? [], [
                    'cancelled_at' => now(),
                    'cancelled_by' => Auth::id(),
                    'cancellation_reason' => $reason
                ])
            ]);

            return true;
        }, 'Gagal membatalkan transaksi');
    }
}
