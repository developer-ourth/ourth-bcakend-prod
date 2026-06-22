<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPack extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'product_id',
        'name',
        'base_price',
        'discounted_price',
        'wholesale_price',
        'wholesale_discounted_price',
        'min_order_quantity',
        'sku',
        'stock_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'discounted_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'wholesale_discounted_price' => 'decimal:2',
            'min_order_quantity' => 'integer',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getSellingPriceAttribute()
    {
        return $this->discounted_price ?? $this->base_price;
    }
}
