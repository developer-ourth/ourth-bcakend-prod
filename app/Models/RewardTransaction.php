<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'reward_catalog_id',
        'transaction_type',
        'points',
        'points_balance_after',
        'source',
        'source_reference',
        'description',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rewardCatalog(): BelongsTo
    {
        return $this->belongsTo(RewardCatalog::class);
    }
}
