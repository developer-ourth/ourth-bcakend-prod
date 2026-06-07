<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryRoute extends Model
{
    protected $fillable = [
        'route_number',
        'route_date',
        'city',
        'delivery_partner_id',
        'status',
        'total_stops',
        'completed_stops',
        'total_distance_km',
        'estimated_duration_minutes',
        'actual_duration_minutes',
        'waypoints',
        'optimized_order',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'route_date' => 'date',
            'total_distance_km' => 'decimal:2',
            'estimated_duration_minutes' => 'decimal:2',
            'actual_duration_minutes' => 'decimal:2',
            'waypoints' => 'array',
            'optimized_order' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_partner_id');
    }
}
