<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'return_number',
        'return_status',
        'reason',
        'items',
        'refund_initiated',
        'approved_at',
        'picked_up_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'refund_initiated' => 'boolean',
            'approved_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
