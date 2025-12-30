<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-units');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:units,name',
            'short_name' => 'required|string|max:10|unique:units,short_name',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama satuan harus diisi',
            'name.unique' => 'Nama satuan sudah digunakan',
            'short_name.required' => 'Singkatan satuan harus diisi',
            'short_name.unique' => 'Singkatan satuan sudah digunakan',
            'short_name.max' => 'Singkatan maksimal 10 karakter',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Set default values
        $validated['is_active'] = $this->boolean('is_active', true);

        return $validated;
    }
}
