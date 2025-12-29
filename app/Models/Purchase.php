<?php

namespace App\Models;

use App\Traits\HasInvoiceNumber;
use App\Traits\HasStockMutation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, SoftDeletes, HasStockMutation, HasInvoiceNumber;

    protected $fillable = [
        'invoice_number',
        'supplier_id',
        'user_id',
        'purchase_date',
        'subtotal',
        'tax',
        'discount',
        'shipping_cost',
        'grand_total',
        'payment_method',
        'payment_status',
        'paid_amount',
        'due_amount',
        'due_date',
        'notes',
        'metadata'
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'due_date' => 'date',
        'metadata' => 'array'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'reference');
    }

    public function getTotalItemsAttribute()
    {
        return $this->items()->sum('quantity');
    }

    public function getIsPaidAttribute()
    {
        return $this->payment_status === 'paid';
    }

    public function getIsUnpaidAttribute()
    {
        return $this->payment_status === 'unpaid';
    }

    public function getIsPartialAttribute()
    {
        return $this->payment_status === 'partial';
    }

    public function markAsPaid($amount = null)
    {
        $amount = $amount ?? $this->due_amount;

        $this->paid_amount += $amount;
        $this->due_amount -= $amount;

        if ($this->due_amount <= 0) {
            $this->payment_status = 'paid';
        } else {
            $this->payment_status = 'partial';
        }

        $this->save();
    }

    public function scopeToday($query)
    {
        return $query->whereDate('purchase_date', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('purchase_date', now()->month)
            ->whereYear('purchase_date', now()->year);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    public function scopePartial($query)
    {
        return $query->where('payment_status', 'partial');
    }
}
