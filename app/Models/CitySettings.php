<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitySettings extends Model
{
    protected $fillable = [
        'city',
        'state',
        'country',
        'status',
        'launch_date',
        'target_vendors',
        'target_consumers',
        'delivery_radius_km',
        'min_order_value',
        'delivery_charge',
        'free_delivery_above',
        'active_features',
        'restricted_features',
        'notes',
        'city_manager_id',
    ];

    protected function casts(): array
    {
        return [
            'launch_date' => 'date',
            'delivery_radius_km' => 'decimal:2',
            'min_order_value' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'free_delivery_above' => 'decimal:2',
            'active_features' => 'array',
            'restricted_features' => 'array',
        ];
    }

    public function cityManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'city_manager_id');
    }
}
