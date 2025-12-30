<?php

namespace App\Imports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class CustomersImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading, SkipsOnError, SkipsOnFailure
{
    use SkipsErrors, SkipsFailures;

    private $updateExisting;
    private $skipErrors;
    private $importStats;

    public function __construct($updateExisting = false, $skipErrors = false)
    {
        $this->updateExisting = $updateExisting;
        $this->skipErrors = $skipErrors;
        $this->importStats = [
            'success' => 0,
            'failed' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];
    }

    public function model(array $row)
    {
        try {
            $data = [
                'name' => $row['name'] ?? $row['nama'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? $row['telepon'] ?? $row['hp'] ?? null,
                'address' => $row['address'] ?? $row['alamat'] ?? null,
                'birth_date' => isset($row['birth_date']) ? $this->parseDate($row['birth_date']) : null,
                'gender' => isset($row['gender']) ? $this->parseGender($row['gender']) : null,
                'is_active' => isset($row['is_active']) ? (bool)$row['is_active'] : true,
            ];

            // Generate member ID if not provided
            if (empty($row['member_id']) && !empty($data['phone'])) {
                $data['member_id'] = 'MEM' . substr($data['phone'], -6);
            } elseif (isset($row['member_id'])) {
                $data['member_id'] = $row['member_id'];
            }

            // Check if customer exists (by email or phone)
            $existingCustomer = null;
            if (!empty($data['email'])) {
                $existingCustomer = Customer::where('email', $data['email'])->first();
            }

            if (!$existingCustomer && !empty($data['phone'])) {
                $existingCustomer = Customer::where('phone', $data['phone'])->first();
            }

            if ($existingCustomer && $this->updateExisting) {
                // Update existing customer
                $existingCustomer->update($data);
                $this->importStats['updated']++;
                return null;
            } elseif (!$existingCustomer) {
                // Create new customer
                $this->importStats['success']++;
                return new Customer($data);
            } else {
                // Skip existing customer
                $this->importStats['skipped']++;
                return null;
            }
        } catch (\Exception $e) {
            $this->importStats['failed']++;

            if ($this->skipErrors) {
                return null;
            }

            throw $e;
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:20|unique:customers,phone',
            'member_id' => 'nullable|string|max:50|unique:customers,member_id',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Nama pelanggan harus diisi',
            'email.unique' => 'Email sudah digunakan',
            'phone.unique' => 'Nomor telepon sudah digunakan',
            'member_id.unique' => 'Member ID sudah digunakan',
        ];
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    private function parseDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            if (is_numeric($date)) {
                // Excel timestamp
                $unixDate = ($date - 25569) * 86400;
                return gmdate("Y-m-d", $unixDate);
            }

            return date('Y-m-d', strtotime($date));
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseGender($gender)
    {
        $gender = strtolower(trim($gender));

        if (in_array($gender, ['male', 'laki-laki', 'laki', 'pria', 'man'])) {
            return 'male';
        } elseif (in_array($gender, ['female', 'perempuan', 'wanita', 'woman'])) {
            return 'female';
        } else {
            return 'other';
        }
    }

    public function getImportStats(): array
    {
        return $this->importStats;
    }
}
