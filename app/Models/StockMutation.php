<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'mutation_type',
        'quantity',
        'previous_stock',
        'current_stock',
        'reference_type',
        'reference_id',
        'notes',
        'user_id'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'previous_stock' => 'integer',
        'current_stock' => 'integer'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function getIsInAttribute()
    {
        return $this->mutation_type === 'in';
    }

    public function getIsOutAttribute()
    {
        return $this->mutation_type === 'out';
    }

    public function scopeIn($query)
    {
        return $query->where('mutation_type', 'in');
    }

    public function scopeOut($query)
    {
        return $query->where('mutation_type', 'out');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }
}
