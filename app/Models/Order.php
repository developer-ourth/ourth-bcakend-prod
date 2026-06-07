<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'order_number',
        'uuid',
        'vendor_id',
        'buyer_vendor_id',
        'order_type',
        'buyer_gstin',
        'order_status',
        'payment_status',
        'subtotal',
        'discount_amount',
        'delivery_charge',
        'tax_amount',
        'total_amount',
        'delivery_address_line1',
        'delivery_address_line2',
        'delivery_city',
        'delivery_state',
        'delivery_postal_code',
        'delivery_country',
        'delivery_phone',
        'customer_notes',
        'admin_notes',
        'confirmed_at',
        'dispatched_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'order_type' => 'string',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }

            if (empty($order->order_number)) {
                $order->order_number = 'ORD-'.strtoupper(Str::random(10));
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * The vendor that placed (bought) this order — null for future B2C consumers.
     */
    public function buyerVendor()
    {
        return $this->belongsTo(Vendor::class, 'buyer_vendor_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function dispatchSlip()
    {
        return $this->hasOne(DispatchSlip::class);
    }

    public function ratings()
    {
        return $this->morphMany(Rating::class, 'ratable');
    }

    public function refund()
    {
        return $this->hasOne(Refund::class);
    }

    public function return()
    {
        return $this->hasOne(ReturnRequest::class);
    }

    public function scopePending($query)
    {
        return $query->where('order_status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('order_status', 'confirmed');
    }

    public function scopeDelivered($query)
    {
        return $query->where('order_status', 'delivered');
    }

    public function scopeCancelled($query)
    {
        return $query->where('order_status', 'cancelled');
    }
}
