<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-customers');
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')->id;

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers')->ignore($customerId)
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('customers')->ignore($customerId)
            ],
            'member_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers')->ignore($customerId)
            ],
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

        return $validated;
    }
}
