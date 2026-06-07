<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecyclingRecord extends Model
{
    protected $fillable = [
        'record_number',
        'facility_name',
        'facility_city',
        'processing_date',
        'input_weight_kg',
        'recycled_weight_kg',
        'rejected_weight_kg',
        'material_type',
        'recycling_efficiency_percent',
        'co2_saved_kg',
        'certificate_url',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'processing_date' => 'date',
            'input_weight_kg' => 'decimal:2',
            'recycled_weight_kg' => 'decimal:2',
            'rejected_weight_kg' => 'decimal:2',
            'recycling_efficiency_percent' => 'decimal:2',
            'co2_saved_kg' => 'decimal:2',
        ];
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
