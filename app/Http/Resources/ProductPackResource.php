<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductPackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'base_price' => $this->base_price,
            'discounted_price' => $this->discounted_price,
            'wholesale_price' => $this->wholesale_price,
            'wholesale_discounted_price' => $this->wholesale_discounted_price,
            'min_order_quantity' => $this->min_order_quantity,
            'sku' => $this->sku,
            'stock_quantity' => $this->stock_quantity,
            'is_active' => $this->is_active,
        ];
    }
}
