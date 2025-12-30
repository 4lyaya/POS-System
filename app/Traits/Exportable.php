<?php

namespace App\Traits;

use Maatwebsite\Excel\Facades\Excel;

trait Exportable
{
    public function exportToExcel($exportClass, $filename, $data = [])
    {
        $export = new $exportClass(...$data);

        return Excel::download($export, $filename);
    }

    public function exportToCsv($exportClass, $filename, $data = [])
    {
        $export = new $exportClass(...$data);

        return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function getExportFilename($prefix, $extension = 'xlsx')
    {
        $timestamp = now()->format('Y-m-d_H-i');
        return "{$prefix}_{$timestamp}.{$extension}";
    }

    public function prepareExportData($query, $columns = [])
    {
        $data = [];

        foreach ($query->cursor() as $item) {
            $row = [];

            foreach ($columns as $column => $header) {
                if (str_contains($column, '.')) {
                    // Handle relationship columns
                    $value = data_get($item, $column);
                } elseif (method_exists($this, 'get' . ucfirst($column) . 'Column')) {
                    // Handle custom column methods
                    $method = 'get' . ucfirst($column) . 'Column';
                    $value = $this->$method($item);
                } else {
                    // Handle regular columns
                    $value = $item->$column;
                }

                $row[$header] = $value;
            }

            $data[] = $row;
        }

        return $data;
    }
}
