<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'sku' => $this->sku,
            'category_id' => $this->category_id,
            'category' => $this->resource->relationLoaded('category') && $this->resource->getRelationValue('category') !== null
                ? new CategoryResource($this->resource->getRelationValue('category'))
                : null,
            'category_label' => $this->getRawOriginal('category'),
            'sub_category' => $this->sub_category,
            'base_price' => $this->base_price,
            'discounted_price' => $this->discounted_price,
            'primary_image_url' => $this->primary_image_url,
            'secondary_images' => $this->secondary_images ?? [],
            'unit' => $this->unit,
            'stock_quantity' => $this->stock_quantity,
            'weight_grams' => $this->weight_grams,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor ? [
                'id' => $this->vendor->id,
                'business_name' => $this->vendor->business_name,
            ] : null),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
