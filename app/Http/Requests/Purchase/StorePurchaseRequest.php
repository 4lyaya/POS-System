<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-purchases');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'invoice_number' => 'nullable|string|max:50|unique:purchases,invoice_number',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expired_date' => 'nullable|date',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'credit'])],
            'payment_status' => ['required', Rule::in(['paid', 'partial', 'unpaid'])],
            'paid_amount' => 'required|numeric|min:0',
            'due_amount' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date|after_or_equal:purchase_date',
            'notes' => 'nullable|string|max:500',
            'update_product_price' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.exists' => 'Supplier tidak ditemukan',
            'purchase_date.required' => 'Tanggal pembelian harus diisi',
            'purchase_date.date' => 'Format tanggal tidak valid',
            'invoice_number.unique' => 'Nomor invoice sudah digunakan',
            'items.required' => 'Minimal satu item harus ditambahkan',
            'items.*.product_id.required' => 'Produk harus dipilih',
            'items.*.product_id.exists' => 'Produk tidak ditemukan',
            'items.*.quantity.min' => 'Quantity minimal 1',
            'items.*.unit_price.min' => 'Harga tidak valid',
            'payment_method.in' => 'Metode pembayaran tidak valid',
            'payment_status.in' => 'Status pembayaran tidak valid',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo harus setelah atau sama dengan tanggal pembelian',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate paid amount based on payment status
            if ($this->input('payment_status') === 'paid') {
                $paidAmount = $this->input('paid_amount', 0);
                $grandTotal = $this->input('grand_total', 0);

                if ($paidAmount < $grandTotal) {
                    $validator->errors()->add(
                        'paid_amount',
                        'Untuk status "Lunas", jumlah pembayaran harus sama dengan total'
                    );
                }
            }

            // Validate due amount calculation
            if ($this->has('due_amount')) {
                $grandTotal = $this->input('grand_total', 0);
                $paidAmount = $this->input('paid_amount', 0);
                $dueAmount = $this->input('due_amount', 0);

                $calculatedDue = $grandTotal - $paidAmount;

                if (abs($dueAmount - $calculatedDue) > 0.01) { // Allow small floating point difference
                    $validator->errors()->add(
                        'due_amount',
                        'Jumlah hutang tidak sesuai dengan perhitungan (Total: ' . $grandTotal . ' - Dibayar: ' . $paidAmount . ' = ' . $calculatedDue . ')'
                    );
                }
            }
        });
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Set default values
        $validated['user_id'] = auth()->id();
        $validated['tax'] = $validated['tax'] ?? 0;
        $validated['discount'] = $validated['discount'] ?? 0;
        $validated['shipping_cost'] = $validated['shipping_cost'] ?? 0;
        $validated['paid_amount'] = $validated['paid_amount'] ?? 0;

        // Calculate due amount if not provided
        if (!isset($validated['due_amount'])) {
            $validated['due_amount'] = $validated['grand_total'] - $validated['paid_amount'];
        }

        return $validated;
    }
}
