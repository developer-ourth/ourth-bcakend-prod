<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Rating extends Model
{
    protected $fillable = [
        'ratable_type',
        'ratable_id',
        'reviewer_id',
        'rating',
        'review',
        'review_photos',
        'is_verified',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'review_photos' => 'array',
            'is_verified' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function ratable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
