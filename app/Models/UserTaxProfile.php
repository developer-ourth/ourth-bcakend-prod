<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTaxProfile extends Model
{
    protected $fillable = [
        'user_id',
        'is_gst_registered',
        'gstin',
        'legal_business_name',
    ];

    protected function casts(): array
    {
        return [
            'is_gst_registered' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
