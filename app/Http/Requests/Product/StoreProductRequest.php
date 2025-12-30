<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-products');
    }
    
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products')->whereNull('deleted_at')
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products')->whereNull('deleted_at')
            ],
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0|gt:purchase_price',
            'wholesale_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'weight' => 'nullable|numeric|min:0',
            'dimension' => 'nullable|string|max:100',
            'expired_date' => 'nullable|date',
            'is_active' => 'boolean',
        ];
    }
    
    public function messages(): array
    {
        return [
            'code.unique' => 'Kode produk sudah digunakan',
            'barcode.unique' => 'Barcode sudah digunakan',
            'selling_price.gt' => 'Harga jual harus lebih besar dari harga beli',
            'image.max' => 'Ukuran gambar maksimal 2MB',
            'images.*.max' => 'Ukuran setiap gambar maksimal 2MB',
        ];
    }
    
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Set default values
        $validated['is_active'] = $this->boolean('is_active', true);
        $validated['stock'] = $validated['stock'] ?? 0;
        $validated['min_stock'] = $validated['min_stock'] ?? 10;
        
        return $validated;
    }
}
