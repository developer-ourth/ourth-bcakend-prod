<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'vendor_id',
        'status',
        'total_amount',
        'total_items',
        'last_activity_at',
        'converted_at',
        'converted_to_order_id',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'last_activity_at' => 'datetime',
            'converted_at' => 'datetime',
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
        return $this->hasMany(CartItem::class);
    }
}
