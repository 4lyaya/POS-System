<?php

namespace App\Traits;

trait HasSearch
{
    public function scopeSearch($query, $search, $columns = [])
    {
        if (empty($search) || empty($columns)) {
            return $query;
        }

        return $query->where(function ($q) use ($search, $columns) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $q->where($column, 'like', "%{$search}%");
                } else {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            }
        });
    }

    public function scopeSearchWithRelations($query, $search, $relations = [])
    {
        if (empty($search) || empty($relations)) {
            return $query;
        }

        return $query->where(function ($q) use ($search, $relations) {
            foreach ($relations as $relation => $columns) {
                $q->orWhereHas($relation, function ($subQuery) use ($search, $columns) {
                    foreach ($columns as $index => $column) {
                        if ($index === 0) {
                            $subQuery->where($column, 'like', "%{$search}%");
                        } else {
                            $subQuery->orWhere($column, 'like', "%{$search}%");
                        }
                    }
                });
            }
        });
    }
}
