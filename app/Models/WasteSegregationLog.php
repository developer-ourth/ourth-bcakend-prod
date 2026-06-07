<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteSegregationLog extends Model
{
    protected $fillable = [
        'waste_collection_id',
        'dry_waste_kg',
        'wet_waste_kg',
        'plastic_waste_kg',
        'e_waste_kg',
        'hazardous_waste_kg',
        'other_waste_kg',
        'logged_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dry_waste_kg' => 'decimal:2',
            'wet_waste_kg' => 'decimal:2',
            'plastic_waste_kg' => 'decimal:2',
            'e_waste_kg' => 'decimal:2',
            'hazardous_waste_kg' => 'decimal:2',
            'other_waste_kg' => 'decimal:2',
            'total_weight_kg' => 'decimal:2',
        ];
    }

    public function wasteCollection(): BelongsTo
    {
        return $this->belongsTo(WasteCollection::class);
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
