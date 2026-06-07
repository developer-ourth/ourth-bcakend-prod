<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpactMetric extends Model
{
    protected $fillable = [
        'metric_date',
        'city',
        'plastic_avoided_kg',
        'landfill_reduction_kg',
        'co2_saved_kg',
        'trees_saved_equivalent',
        'dustbins_active',
        'collections_completed',
        'total_waste_collected_kg',
        'recycling_rate_percent',
        'vendors_using_eco_products',
        'eco_orders_count',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'plastic_avoided_kg' => 'decimal:2',
            'landfill_reduction_kg' => 'decimal:2',
            'co2_saved_kg' => 'decimal:2',
            'trees_saved_equivalent' => 'decimal:2',
            'total_waste_collected_kg' => 'decimal:2',
            'recycling_rate_percent' => 'decimal:2',
        ];
    }
}
