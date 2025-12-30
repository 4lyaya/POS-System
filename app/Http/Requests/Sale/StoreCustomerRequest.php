<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-customers');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:20|unique:customers,phone',
            'member_id' => 'nullable|string|max:50|unique:customers,member_id',
            'address' => 'nullable|string|max:500',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama customer harus diisi',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah digunakan',
            'phone.unique' => 'Nomor telepon sudah digunakan',
            'member_id.unique' => 'ID member sudah digunakan',
            'birth_date.date' => 'Format tanggal lahir tidak valid',
            'gender.in' => 'Jenis kelamin tidak valid',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Set default values
        $validated['is_active'] = $this->boolean('is_active', true);
        $validated['points'] = 0;
        $validated['total_purchases'] = 0;

        return $validated;
    }
}
