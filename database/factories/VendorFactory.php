<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'           => \App\Models\User::factory(),
            'vendor_code'       => strtoupper(fake()->unique()->bothify('VND-####')),
            'business_name'     => fake()->company(),
            'business_category' => 'food',
            'kyc_status'        => 'pending',
            'city'              => fake()->city(),
            'state'             => fake()->state(),
            'postal_code'       => fake()->postcode(),
            'country'           => 'India',
        ];
    }

    /**
     * Automatically create the VendorApproval record that the approval
     * workflow depends on — mirrors what VendorService::register() does.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Vendor $vendor) {
            \App\Models\VendorApproval::create([
                'vendor_id'      => $vendor->id,
                'approval_stage' => 'pending_documents',
            ]);
        });
    }
}
