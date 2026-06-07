<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'target_audience',
        'status',
        'budget',
        'amount_spent',
        'city',
        'start_date',
        'end_date',
        'impressions',
        'clicks',
        'conversions',
        'signups_from_campaign',
        'promo_code',
        'meta',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'amount_spent' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'meta' => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }
}
