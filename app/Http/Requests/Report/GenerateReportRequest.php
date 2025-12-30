<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('view-reports');
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:sales,purchases,stock,financial',
            'format' => 'required|in:pdf,excel,html',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'customer_id' => 'nullable|exists:customers,id',
            'user_id' => 'nullable|exists:users,id',
            'payment_method' => 'nullable|string',
            'payment_status' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Jenis laporan tidak valid',
            'format.in' => 'Format ekspor tidak valid',
            'end_date.after_or_equal' => 'Tanggal akhir harus setelah atau sama dengan tanggal awal',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Set default date range if not provided
        if (empty($validated['start_date'])) {
            $validated['start_date'] = now()->startOfMonth()->toDateString();
        }

        if (empty($validated['end_date'])) {
            $validated['end_date'] = now()->endOfMonth()->toDateString();
        }

        return $validated;
    }
}
