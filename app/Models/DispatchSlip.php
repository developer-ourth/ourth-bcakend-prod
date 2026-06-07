<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchSlip extends Model
{
    protected $fillable = [
        'dispatch_number',
        'order_id',
        'status',
        'approved_by',
        'approved_at',
        'packed_by',
        'packed_at',
        'handed_over_by',
        'handed_over_at',
        'packing_notes',
        'total_packages',
        'total_weight_kg',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'packed_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'total_weight_kg' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function packedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by');
    }

    public function handedOverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }
}
