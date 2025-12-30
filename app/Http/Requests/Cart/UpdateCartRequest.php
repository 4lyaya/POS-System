<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-sales');
    }

    public function rules(): array
    {
        return [
            'quantity' => 'required|integer|min:0',
            'discount' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'Quantity harus diisi',
            'quantity.min' => 'Quantity minimal 0',
            'discount.numeric' => 'Diskon harus berupa angka',
            'discount.min' => 'Diskon tidak boleh negatif',
        ];
    }

    public function withValidator($validator)
    {
        $productId = $this->route('productId');

        $validator->after(function ($validator) use ($productId) {
            $quantity = $this->input('quantity', 0);

            if ($quantity > 0) {
                $product = \App\Models\Product::find($productId);

                if ($product) {
                    // Check stock availability
                    if ($product->stock < $quantity) {
                        $validator->errors()->add(
                            'quantity',
                            "Stok {$product->name} tidak mencukupi. Stok tersedia: {$product->stock}"
                        );
                    }

                    // Check discount
                    $discount = $this->input('discount', 0);
                    $maxDiscount = $product->selling_price * $quantity;

                    if ($discount > $maxDiscount) {
                        $validator->errors()->add(
                            'discount',
                            "Diskon tidak boleh lebih besar dari total harga item (Rp " . number_format($maxDiscount, 0, ',', '.') . ")"
                        );
                    }
                } else {
                    $validator->errors()->add(
                        'product_id',
                        "Produk tidak ditemukan"
                    );
                }
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
