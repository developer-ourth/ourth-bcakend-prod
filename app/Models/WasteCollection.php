<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WasteCollection extends Model
{
    protected $fillable = [
        'collection_number',
        'dustbin_id',
        'collected_by',
        'status',
        'scheduled_date',
        'scheduled_time',
        'started_at',
        'completed_at',
        'waste_weight_kg',
        'latitude',
        'longitude',
        'photo_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'waste_weight_kg' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function dustbin(): BelongsTo
    {
        return $this->belongsTo(Dustbin::class);
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function segregationLog(): HasOne
    {
        return $this->hasOne(WasteSegregationLog::class);
    }
}
