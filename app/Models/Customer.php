<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'member_id',
        'address',
        'birth_date',
        'gender',
        'points',
        'total_purchases',
        'last_purchase',
        'is_active'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'points' => 'decimal:2',
        'total_purchases' => 'decimal:2',
        'last_purchase' => 'date',
        'is_active' => 'boolean'
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function getTotalTransactionsAttribute()
    {
        return $this->sales()->count();
    }

    public function getAverageTransactionAttribute()
    {
        $count = $this->total_transactions;
        return $count > 0 ? $this->total_purchases / $count : 0;
    }

    public function addPoints($points)
    {
        $this->points += $points;
        $this->save();
    }

    public function deductPoints($points)
    {
        if ($this->points >= $points) {
            $this->points -= $points;
            $this->save();
            return true;
        }
        return false;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHasPoints($query)
    {
        return $query->where('points', '>', 0);
    }
}
