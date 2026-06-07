<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the single Ourth distributor vendor that owns all catalog products.
 *
 * This seeder is idempotent — safe to run multiple times.
 * The distributor vendor is identified by is_distributor = true.
 */
class DistributorSeeder extends Seeder
{
    public function run(): void
    {
        // Distributor user — internal account, never logs in via mobile app
        $user = User::updateOrCreate(
            ['email' => 'distributor@ourth.app'],
            [
                'name' => 'Ourth Distribution',
                'password' => Hash::make(bin2hex(random_bytes(32))), // random, not usable for login
                'role' => 'admin',
                'user_type' => 'vendor',
                'email_verified_at' => now(),
            ]
        );

        // Distributor vendor record — the single seller in the B2D model
        Vendor::updateOrCreate(
            ['user_id' => $user->id],
            [
                'is_distributor' => true,
                'vendor_code' => 'OURTH',
                'business_name' => 'Ourth Distribution',
                'business_category' => 'Eco-Friendly Disposables',
                'description' => 'Official Ourth product catalogue. Distributor of all eco-friendly products.',
                'kyc_status' => 'verified',
                'kyc_verified_at' => now(),
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
            ]
        );

        $this->command->info('Distributor vendor seeded (vendor_code: OURTH, email: distributor@ourth.app).');
    }
}
