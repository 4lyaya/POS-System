<?php

namespace App\Models;

use App\Traits\HasFilter;
use App\Traits\HasInvoiceNumber;
use App\Traits\HasSearch;
use App\Traits\HasStockMutation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes, HasStockMutation, HasInvoiceNumber, HasSearch, HasFilter;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'user_id',
        'sale_date',
        'items_count',
        'subtotal',
        'tax',
        'discount',
        'service_charge',
        'grand_total',
        'payment_method',
        'payment_status',
        'paid_amount',
        'change_amount',
        'notes',
        'metadata'
    ];

    protected $casts = [
        'sale_date' => 'date',
        'items_count' => 'integer',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'metadata' => 'array'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'reference');
    }

    public function getProfitAttribute()
    {
        $profit = 0;
        foreach ($this->items as $item) {
            $profit += ($item->unit_price - $item->product->purchase_price) * $item->quantity;
        }
        return $profit;
    }

    public function getProfitMarginAttribute()
    {
        if ($this->grand_total == 0) return 0;
        return ($this->profit / $this->grand_total) * 100;
    }

    public function getIsPaidAttribute()
    {
        return $this->payment_status === 'paid';
    }

    public function scopeToday($query)
    {
        return $query->whereDate('sale_date', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('sale_date', now()->month)
            ->whereYear('sale_date', now()->year);
    }

    public function scopeByCashier($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    public static function getDailyReport($date = null)
    {
        $date = $date ?? today();

        return self::whereDate('sale_date', $date)
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(grand_total) as total_sales,
                SUM(paid_amount) as total_paid,
                AVG(grand_total) as average_transaction,
                SUM(items_count) as total_items_sold
            ')
            ->first();
    }
}
