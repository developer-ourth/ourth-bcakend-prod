<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'vendor_id',
        'category_id',
        'name',
        'description',
        'sku',
        'barcode',
        'category',
        'sub_category',
        'base_price',
        'discounted_price',
        'cost_price',
        'wholesale_price',
        'min_order_quantity',
        'primary_image_url',
        'secondary_images',
        'weight_grams',
        'dimensions_cm',
        'stock_quantity',
        'unit',
        'is_active',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'secondary_images' => 'json',
            'dimensions_cm' => 'json',
            'base_price' => 'decimal:2',
            'discounted_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'min_order_quantity' => 'integer',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function ratings()
    {
        return $this->morphMany(Rating::class, 'ratable');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function getAvailableStockAttribute()
    {
        return $this->inventory->available_stock ?? 0;
    }

    public function getSellingPriceAttribute()
    {
        return $this->discounted_price ?? $this->base_price;
    }
}
