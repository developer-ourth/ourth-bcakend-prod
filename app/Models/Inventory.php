<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    protected $table = 'inventory';

    protected $fillable = [
        'product_id',
        'vendor_id',
        'current_stock',
        'reserved_stock',
        'minimum_stock_level',
        'reorder_quantity',
        'last_restocked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_restocked_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->minimum_stock_level;
    }
}
