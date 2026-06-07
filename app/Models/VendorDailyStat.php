<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDailyStat extends Model
{
    protected $fillable = [
        'vendor_id',
        'stats_date',
        'total_orders',
        'total_revenue',
        'delivered_orders',
        'cancelled_orders',
        'returned_orders',
        'average_order_value',
        'unique_customers',
    ];

    protected function casts(): array
    {
        return [
            'stats_date' => 'date',
            'total_revenue' => 'decimal:2',
            'average_order_value' => 'decimal:2',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
