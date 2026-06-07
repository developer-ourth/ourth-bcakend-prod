<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'delivery_partner_id',
        'delivery_status',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
        'current_latitude',
        'current_longitude',
        'delivery_otp',
        'otp_verified',
        'otp_verified_at',
        'proof_of_delivery_url',
        'delivery_notes',
        'distance_km',
        'estimated_time_minutes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'otp_verified_at' => 'datetime',
            'otp_verified' => 'boolean',
            'current_latitude' => 'decimal:8',
            'current_longitude' => 'decimal:8',
            'distance_km' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_partner_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(DeliveryLocation::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(DeliveryVerification::class);
    }
}
