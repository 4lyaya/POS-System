<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-sales');
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'discount' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk harus dipilih',
            'product_id.exists' => 'Produk tidak ditemukan',
            'quantity.required' => 'Quantity harus diisi',
            'quantity.min' => 'Quantity minimal 1',
            'discount.numeric' => 'Diskon harus berupa angka',
            'discount.min' => 'Diskon tidak boleh negatif',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $productId = $this->input('product_id');
            $quantity = $this->input('quantity', 1);

            $product = \App\Models\Product::find($productId);

            if ($product) {
                // Check stock availability
                if ($product->stock < $quantity) {
                    $validator->errors()->add(
                        'quantity',
                        "Stok {$product->name} tidak mencukupi. Stok tersedia: {$product->stock}"
                    );
                }

                // Check if product is active
                if (!$product->is_active) {
                    $validator->errors()->add(
                        'product_id',
                        "Produk {$product->name} tidak aktif"
                    );
                }
            }

            // Check discount
            $discount = $this->input('discount', 0);
            $productPrice = $product ? $product->selling_price : 0;
            $maxDiscount = $productPrice * $quantity;

            if ($discount > $maxDiscount) {
                $validator->errors()->add(
                    'discount',
                    "Diskon tidak boleh lebih besar dari total harga item (Rp " . number_format($maxDiscount, 0, ',', '.') . ")"
                );
            }
        });
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Set default values
        $validated['discount'] = $validated['discount'] ?? 0;

        return $validated;
    }
}
