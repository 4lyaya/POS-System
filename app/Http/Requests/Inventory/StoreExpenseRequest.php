<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-purchases'); // Use purchase permission for expenses
    }

    public function rules(): array
    {
        return [
            'expense_date' => 'required|date',
            'category' => 'required|string|max:100',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'expense_date.required' => 'Tanggal pengeluaran harus diisi',
            'expense_date.date' => 'Format tanggal tidak valid',
            'category.required' => 'Kategori pengeluaran harus diisi',
            'category.max' => 'Kategori maksimal 100 karakter',
            'description.required' => 'Deskripsi pengeluaran harus diisi',
            'description.max' => 'Deskripsi maksimal 255 karakter',
            'amount.required' => 'Jumlah pengeluaran harus diisi',
            'amount.numeric' => 'Jumlah harus berupa angka',
            'amount.min' => 'Jumlah tidak boleh negatif',
            'notes.max' => 'Catatan maksimal 500 karakter',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Set default values
        $validated['user_id'] = auth()->id();

        return $validated;
    }
}
