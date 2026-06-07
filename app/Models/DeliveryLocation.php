<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryLocation extends Model
{
    protected $fillable = [
        'delivery_id',
        'latitude',
        'longitude',
        'accuracy_meters',
        'speed_kmh',
        'recorded_at',
        'address_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'accuracy_meters' => 'decimal:2',
            'speed_kmh' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
