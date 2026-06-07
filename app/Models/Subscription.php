<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'vendor_id',
        'plan_name',
        'frequency',
        'status',
        'plan_price',
        'start_date',
        'end_date',
        'next_delivery_date',
        'delivery_address',
        'total_deliveries',
        'deliveries_completed',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'plan_price' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_delivery_date' => 'date',
            'delivery_address' => 'array',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }
}
