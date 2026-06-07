<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SustainabilityScore extends Model
{
    protected $fillable = [
        'user_id',
        'green_points',
        'carbon_points',
        'total_points',
        'tier',
        'plastic_avoided_kg',
        'co2_saved_kg',
        'eco_orders_count',
        'bins_used_count',
    ];

    protected function casts(): array
    {
        return [
            'plastic_avoided_kg' => 'decimal:2',
            'co2_saved_kg' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rewardTransactions(): HasMany
    {
        return $this->hasMany(RewardTransaction::class, 'user_id', 'user_id');
    }
}
