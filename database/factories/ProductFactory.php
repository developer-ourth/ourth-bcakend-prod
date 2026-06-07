<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id'   => \App\Models\Vendor::factory(),
            'name'        => fake()->words(3, true),
            'description' => fake()->sentence(),
            'sku'         => fake()->unique()->bothify('SKU-#####'),
            'category'    => 'food',
            'base_price'  => fake()->randomFloat(2, 10, 500),
            'is_active'   => true,
            'is_featured' => false,
        ];
    }
}
