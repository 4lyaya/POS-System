<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
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
use Illuminate\Support\Facades\Validator;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading, SkipsOnError, SkipsOnFailure
{
    use SkipsErrors, SkipsFailures;

    private $updateExisting;
    private $skipErrors;
    private $categories;
    private $units;
    private $importStats;

    public function __construct($updateExisting = false, $skipErrors = false)
    {
        $this->updateExisting = $updateExisting;
        $this->skipErrors = $skipErrors;
        $this->categories = Category::all()->keyBy('name');
        $this->units = Unit::all()->keyBy('name');
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
            // Prepare data
            $data = [
                'code' => $row['code'] ?? $this->generateCode($row),
                'barcode' => $row['barcode'] ?? null,
                'name' => $row['name'],
                'purchase_price' => $row['purchase_price'] ?? 0,
                'selling_price' => $row['selling_price'] ?? 0,
                'stock' => $row['stock'] ?? 0,
                'min_stock' => $row['min_stock'] ?? 10,
                'description' => $row['description'] ?? null,
                'is_active' => isset($row['is_active']) ? (bool)$row['is_active'] : true,
            ];

            // Handle category
            if (isset($row['category'])) {
                $categoryName = trim($row['category']);
                if ($this->categories->has($categoryName)) {
                    $data['category_id'] = $this->categories[$categoryName]->id;
                } else {
                    // Create new category
                    $category = Category::create([
                        'name' => $categoryName,
                        'slug' => Str::slug($categoryName),
                        'is_active' => true,
                    ]);
                    $this->categories->put($categoryName, $category);
                    $data['category_id'] = $category->id;
                }
            } elseif (isset($row['category_id'])) {
                $data['category_id'] = $row['category_id'];
            }

            // Handle unit
            if (isset($row['unit'])) {
                $unitName = trim($row['unit']);
                if ($this->units->has($unitName)) {
                    $data['unit_id'] = $this->units[$unitName]->id;
                } else {
                    // Create new unit
                    $unit = Unit::create([
                        'name' => $unitName,
                        'short_name' => $unitName,
                        'is_active' => true,
                    ]);
                    $this->units->put($unitName, $unit);
                    $data['unit_id'] = $unit->id;
                }
            } elseif (isset($row['unit_id'])) {
                $data['unit_id'] = $row['unit_id'];
            }

            // Check if product exists
            $existingProduct = Product::where('code', $data['code'])->first();

            if ($existingProduct && $this->updateExisting) {
                // Update existing product
                $existingProduct->update($data);
                $this->importStats['updated']++;
                return null;
            } elseif (!$existingProduct) {
                // Create new product
                $this->importStats['success']++;
                return new Product($data);
            } else {
                // Skip existing product
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
            'code' => 'nullable|string|max:50|unique:products,code',
            'purchase_price' => 'nullable|numeric|min:0',
            'selling_price' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    $purchasePrice = request()->input('purchase_price', 0);
                    if ($value > 0 && $purchasePrice > 0 && $value <= $purchasePrice) {
                        $fail('Harga jual harus lebih besar dari harga beli');
                    }
                },
            ],
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Nama produk harus diisi',
            'code.unique' => 'Kode produk sudah digunakan',
            'selling_price.min' => 'Harga jual minimal 0',
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

    private function generateCode($row): string
    {
        $prefix = 'PRD';
        $namePart = Str::limit(Str::slug($row['name']), 3, '');
        $randomPart = Str::random(3);

        return strtoupper($prefix . '-' . $namePart . '-' . $randomPart);
    }

    public function getImportStats(): array
    {
        return $this->importStats;
    }
}
