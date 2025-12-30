<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-stock');
    }

    public function rules(): array
    {
        return [
            'adjustment_date' => 'required|date',
            'adjustment_type' => ['required', Rule::in(['addition', 'subtraction', 'correction'])],
            'reason' => 'required|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'adjustment_date.required' => 'Tanggal penyesuaian harus diisi',
            'adjustment_date.date' => 'Format tanggal tidak valid',
            'adjustment_type.required' => 'Tipe penyesuaian harus dipilih',
            'adjustment_type.in' => 'Tipe penyesuaian tidak valid',
            'reason.required' => 'Alasan penyesuaian harus diisi',
            'reason.max' => 'Alasan maksimal 500 karakter',
            'items.required' => 'Minimal satu item harus ditambahkan',
            'items.*.product_id.required' => 'Produk harus dipilih',
            'items.*.product_id.exists' => 'Produk tidak ditemukan',
            'items.*.quantity.required' => 'Quantity harus diisi',
            'items.*.quantity.min' => 'Quantity minimal 1',
            'items.*.notes.max' => 'Catatan maksimal 200 karakter',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate stock availability for subtraction
            if ($this->input('adjustment_type') === 'subtraction') {
                foreach ($this->input('items', []) as $index => $item) {
                    $product = \App\Models\Product::find($item['product_id']);
                    if ($product && $product->stock < $item['quantity']) {
                        $validator->errors()->add(
                            "items.{$index}.quantity",
                            "Stok {$product->name} tidak mencukupi untuk pengurangan. Stok tersedia: {$product->stock}"
                        );
                    }
                }
            }

            // Validate correction doesn't set negative stock
            if ($this->input('adjustment_type') === 'correction') {
                foreach ($this->input('items', []) as $index => $item) {
                    if ($item['quantity'] < 0) {
                        $validator->errors()->add(
                            "items.{$index}.quantity",
                            "Stok tidak boleh bernilai negatif"
                        );
                    }
                }
            }
        });
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Set default values
        $validated['user_id'] = auth()->id();

        return $validated;
    }
}
