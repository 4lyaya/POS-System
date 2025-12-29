<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Adjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'adjustment_number',
        'adjustment_date',
        'adjustment_type',
        'reason',
        'user_id'
    ];

    protected $casts = [
        'adjustment_date' => 'date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(AdjustmentItem::class);
    }

    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'reference');
    }

    public function getTotalItemsAttribute()
    {
        return $this->items()->count();
    }

    public function getTotalQuantityAttribute()
    {
        return $this->items()->sum('quantity');
    }
}
