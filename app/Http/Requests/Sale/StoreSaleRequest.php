<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{

    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-sales');
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'qris', 'debit', 'credit'])],
            'paid_amount' => 'required|numeric|min:0',
            'change_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal satu item harus ditambahkan',
            'items.*.product_id.required' => 'Produk harus dipilih',
            'items.*.quantity.min' => 'Quantity minimal 1',
            'payment_method.in' => 'Metode pembayaran tidak valid',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate stock availability
            if ($this->has('items')) {
                foreach ($this->input('items') as $index => $item) {
                    $product = \App\Models\Product::find($item['product_id']);
                    if ($product && $product->stock < $item['quantity']) {
                        $validator->errors()->add(
                            "items.{$index}.quantity",
                            "Stok {$product->name} tidak mencukupi. Stok tersedia: {$product->stock}"
                        );
                    }
                }
            }

            // Validate cash payment
            if ($this->input('payment_method') === 'cash') {
                $paidAmount = $this->input('paid_amount', 0);
                $grandTotal = $this->input('grand_total', 0);

                if ($paidAmount < $grandTotal) {
                    $validator->errors()->add(
                        'paid_amount',
                        'Jumlah pembayaran kurang dari total'
                    );
                }
            }
        });
    }
}
