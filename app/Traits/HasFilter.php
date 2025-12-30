<?php

namespace App\Traits;

trait HasFilter
{
    public function scopeFilter($query, array $filters)
    {
        foreach ($filters as $key => $value) {
            if (method_exists($this, 'scopeFilter' . ucfirst($key))) {
                $scope = 'filter' . ucfirst($key);
                $query->$scope($value);
            } elseif (!empty($value)) {
                $this->applyBasicFilter($query, $key, $value);
            }
        }

        return $query;
    }

    protected function applyBasicFilter($query, $key, $value)
    {
        if (str_ends_with($key, '_from')) {
            $field = str_replace('_from', '', $key);
            $query->where($field, '>=', $value);
        } elseif (str_ends_with($key, '_to')) {
            $field = str_replace('_to', '', $key);
            $query->where($field, '<=', $value);
        } elseif (str_ends_with($key, '_at')) {
            $query->whereDate($key, $value);
        } elseif (is_array($value)) {
            $query->whereIn($key, $value);
        } else {
            $query->where($key, $value);
        }
    }

    public function scopeFilterDateRange($query, $range)
    {
        if (!empty($range['start']) && !empty($range['end'])) {
            $query->whereBetween('created_at', [$range['start'], $range['end']]);
        }

        return $query;
    }

    public function scopeFilterStatus($query, $status)
    {
        if (!empty($status)) {
            $query->where('status', $status);
        }

        return $query;
    }

    public function scopeFilterActive($query, $active)
    {
        if (!is_null($active)) {
            $query->where('is_active', $active);
        }

        return $query;
    }
}
