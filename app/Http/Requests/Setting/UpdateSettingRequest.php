<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('manage-settings');
    }
    
    public function rules(): array
    {
        return [
            'store_name' => 'required|string|max:255',
            'store_address' => 'nullable|string|max:500',
            'store_phone' => 'nullable|string|max:20',
            'store_email' => 'nullable|email|max:255',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'service_charge_rate' => 'nullable|numeric|min:0|max:100',
            'currency' => 'required|string|max:10',
            'currency_symbol' => 'nullable|string|max:5',
            'currency_position' => 'nullable|in:before,after',
            'receipt_header' => 'nullable|string|max:500',
            'receipt_footer' => 'nullable|string|max:500',
            'receipt_print_copy' => 'nullable|integer|min:1|max:3',
            'low_stock_threshold' => 'nullable|integer|min:1',
            'auto_generate_barcode' => 'boolean',
            'default_payment_method' => 'nullable|in:cash,transfer,qris,debit,credit',
            'enable_discount' => 'boolean',
            'enable_tax' => 'boolean',
            'enable_customer_selection' => 'boolean',
            'theme_color' => 'nullable|string|max:50',
            'dashboard_refresh_interval' => 'nullable|integer|min:30|max:300',
        ];
    }
    
    public function messages(): array
    {
        return [
            'store_name.required' => 'Nama toko harus diisi',
            'store_email.email' => 'Format email toko tidak valid',
            'tax_rate.numeric' => 'Tarif pajak harus berupa angka',
            'tax_rate.min' => 'Tarif pajak minimal 0%',
            'tax_rate.max' => 'Tarif pajak maksimal 100%',
            'service_charge_rate.numeric' => 'Tarif biaya layanan harus berupa angka',
            'service_charge_rate.min' => 'Tarif biaya layanan minimal 0%',
            'service_charge_rate.max' => 'Tarif biaya layanan maksimal 100%',
            'currency.required' => 'Mata uang harus dipilih',
            'currency_position.in' => 'Posisi mata uang tidak valid',
            'receipt_print_copy.min' => 'Jumlah salinan struk minimal 1',
            'receipt_print_copy.max' => 'Jumlah salinan struk maksimal 3',
            'low_stock_threshold.min' => 'Batas stok menipis minimal 1',
            'default_payment_method.in' => 'Metode pembayaran default tidak valid',
            'dashboard_refresh_interval.min' => 'Interval refresh dashboard minimal 30 detik',
            'dashboard_refresh_interval.max' => 'Interval refresh dashboard maksimal 300 detik',
        ];
    }
    
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Set default values
        $validated['auto_generate_barcode'] = $this->boolean('auto_generate_barcode', true);
        $validated['enable_discount'] = $this->boolean('enable_discount', true);
        $validated['enable_tax'] = $this->boolean('enable_tax', true);
        $validated['enable_customer_selection'] = $this->boolean('enable_customer_selection', true);
        $validated['currency_symbol'] = $validated['currency_symbol'] ?? 'Rp';
        $validated['currency_position'] = $validated['currency_position'] ?? 'before';
        $validated['receipt_print_copy'] = $validated['receipt_print_copy'] ?? 1;
        $validated['low_stock_threshold'] = $validated['low_stock_threshold'] ?? 10;
        $validated['dashboard_refresh_interval'] = $validated['dashboard_refresh_interval'] ?? 60;
        
        return $validated;
    }
}
