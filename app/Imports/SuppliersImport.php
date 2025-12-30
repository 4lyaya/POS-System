<?php

namespace App\Imports;

use App\Models\Supplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SuppliersImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            Supplier::firstOrCreate(
                [
                    'code' => $row['kode'] ?? null,
                    'email' => $row['email'] ?? null,
                ],
                [
                    'name' => $row['nama'],
                    'phone' => $row['telepon'] ?? null,
                    'address' => $row['alamat'] ?? null,
                    'contact_person' => $row['kontak'] ?? null,
                    'tax_number' => $row['npwp'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
    
    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:255',
            'telepon' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'alamat' => 'nullable|string|max:500',
        ];
    }
}
