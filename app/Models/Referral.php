<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_id',
        'referral_code',
        'referred_as',
        'status',
        'referrer_points_earned',
        'referred_points_earned',
        'campaign_id',
        'signed_up_at',
        'activated_at',
        'rewarded_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'signed_up_at' => 'datetime',
            'activated_at' => 'datetime',
            'rewarded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
