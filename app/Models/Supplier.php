<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'address',
        'contact_person',
        'tax_number',
        'balance',
        'is_active'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function getTotalPurchasesAttribute()
    {
        return $this->purchases()->sum('grand_total');
    }

    public function getTotalPaidAttribute()
    {
        return $this->purchases()->sum('paid_amount');
    }

    public function getTotalDueAttribute()
    {
        return $this->purchases()->sum('due_amount');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHasDebt($query)
    {
        return $query->whereHas('purchases', function ($q) {
            $q->where('payment_status', '!=', 'paid');
        });
    }
}
