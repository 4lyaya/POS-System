<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Models\Product;
use App\Models\StockMutation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $saleData;
    public $userId;

    public function __construct(array $saleData, $userId)
    {
        $this->saleData = $saleData;
        $this->userId = $userId;
    }

    public function handle()
    {
        return DB::transaction(function () {
            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber();

            // Create sale
            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $this->saleData['customer_id'] ?? null,
                'user_id' => $this->userId,
                'sale_date' => now(),
                'items_count' => count($this->saleData['items']),
                'subtotal' => $this->saleData['subtotal'],
                'tax' => $this->saleData['tax'] ?? 0,
                'discount' => $this->saleData['discount'] ?? 0,
                'service_charge' => $this->saleData['service_charge'] ?? 0,
                'grand_total' => $this->saleData['grand_total'],
                'payment_method' => $this->saleData['payment_method'],
                'payment_status' => 'paid',
                'paid_amount' => $this->saleData['paid_amount'],
                'change_amount' => $this->saleData['change_amount'] ?? 0,
                'notes' => $this->saleData['notes'] ?? null,
            ]);

            // Process sale items
            foreach ($this->saleData['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Validate stock
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stok produk {$product->name} tidak mencukupi");
                }

                // Create sale item
                $sale->items()->create([
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
                    'user_id' => $this->userId,
                ]);

                // Fire event for stock update
                event(new \App\Events\ProductStockUpdated(
                    $product,
                    StockMutation::latest()->first(),
                    $oldStock,
                    $product->stock
                ));
            }

            // Fire sale completed event
            event(new \App\Events\SaleCompleted($sale));

            return $sale;
        });
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
}
