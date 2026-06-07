<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardCatalog extends Model
{
    protected $table = 'reward_catalog';

    protected $fillable = [
        'name',
        'description',
        'reward_type',
        'points_required',
        'cashback_amount',
        'discount_percent',
        'image_url',
        'is_active',
        'valid_from',
        'valid_until',
        'total_quantity',
        'redeemed_count',
    ];

    protected function casts(): array
    {
        return [
            'cashback_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RewardTransaction::class);
    }

    public function isAvailable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->total_quantity !== null && $this->redeemed_count >= $this->total_quantity) {
            return false;
        }

        if ($this->valid_until && now()->isAfter($this->valid_until)) {
            return false;
        }

        return true;
    }
}
