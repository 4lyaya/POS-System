<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-sales');
    }
    
    public function rules(): array
    {
        $saleId = $this->route('sale')->id;
        
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'sale_date' => 'required|date',
            'invoice_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('sales')->ignore($saleId)
            ],
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'qris', 'debit', 'credit'])],
            'payment_status' => ['required', Rule::in(['paid', 'partial', 'unpaid', 'cancelled'])],
            'paid_amount' => 'required|numeric|min:0',
            'change_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ];
    }
    
    public function messages(): array
    {
        return [
            'customer_id.exists' => 'Customer tidak ditemukan',
            'sale_date.required' => 'Tanggal penjualan harus diisi',
            'sale_date.date' => 'Format tanggal tidak valid',
            'invoice_number.unique' => 'Nomor invoice sudah digunakan',
            'payment_method.in' => 'Metode pembayaran tidak valid',
            'payment_status.in' => 'Status pembayaran tidak valid',
        ];
    }
    
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate cash payment
            if ($this->input('payment_method') === 'cash') {
                $paidAmount = $this->input('paid_amount', 0);
                $grandTotal = $this->input('grand_total', 0);
                
                if ($paidAmount < $grandTotal) {
                    $validator->errors()->add(
                        'paid_amount',
                        'Untuk pembayaran tunai, jumlah pembayaran tidak boleh kurang dari total'
                    );
                }
            }
            
            // Validate payment status consistency
            if ($this->input('payment_status') === 'paid') {
                $paidAmount = $this->input('paid_amount', 0);
                $grandTotal = $this->input('grand_total', 0);
                
                if ($paidAmount < $grandTotal) {
                    $validator->errors()->add(
                        'paid_amount',
                        'Untuk status "Lunas", jumlah pembayaran harus sama atau lebih dari total'
                    );
                }
            }
        });
    }
    
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Set default values
        $validated['tax'] = $validated['tax'] ?? 0;
        $validated['discount'] = $validated['discount'] ?? 0;
        $validated['service_charge'] = $validated['service_charge'] ?? 0;
        $validated['change_amount'] = $validated['change_amount'] ?? 0;
        
        return $validated;
    }
}
