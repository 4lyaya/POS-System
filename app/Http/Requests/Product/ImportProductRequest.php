<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class ImportProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-products');
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120', // 5MB max
            'import_type' => 'required|in:create,update,both',
            'skip_duplicates' => 'boolean',
            'update_existing' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File import harus diupload',
            'file.mimes' => 'Format file harus Excel (xlsx, xls) atau CSV',
            'file.max' => 'Ukuran file maksimal 5MB',
            'import_type.in' => 'Tipe import tidak valid',
        ];
    }
}
