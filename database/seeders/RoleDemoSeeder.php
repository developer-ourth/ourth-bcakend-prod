<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Campaign;
use App\Models\CitySettings;
use App\Models\Dustbin;
use App\Models\FinancialDailySnapshot;
use App\Models\ImpactMetric;
use App\Models\Order;
use App\Models\Product;
use App\Models\RewardCatalog;
use App\Models\RewardTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApproval;
use App\Models\WasteCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleDemoSeeder extends Seeder
{
    /** @var array<string, array<string, string>> */
    private array $demoUsers = [
        'founder'          => ['name' => 'Pranay Bhargava (Founder)',        'email' => 'pranay@healingourth.com'],
        'vendor'           => ['name' => 'Ravi Kumar (Vendor)',           'email' => 'vendor@ourth.app'],
        'consumer'         => ['name' => 'Priya Sharma (Consumer)',       'email' => 'consumer@ourth.app'],
        'operations'       => ['name' => 'Suresh Nair (Operations)',      'email' => 'operations@ourth.app'],
        'waste_management' => ['name' => 'Deepak Rao (Waste Mgmt)',       'email' => 'waste@ourth.app'],
        'finance'          => ['name' => 'Ananya Singh (Finance)',        'email' => 'finance@ourth.app'],
        'admin'            => ['name' => 'Sahil (Admin)',                 'email' => 'sahilbhargava6@gmail.com'],
        'marketing'        => ['name' => 'Meera Patel (Marketing)',       'email' => 'marketing@ourth.app'],
    ];

    public function run(): void
    {
        // ── 1. Create one user per role ────────────────────────────────────────
        $users = [];
        foreach ($this->demoUsers as $role => $attrs) {
            $users[$role] = User::updateOrCreate(
                ['email' => $attrs['email']],
                [
                    'name'              => $attrs['name'],
                    'password'          => Hash::make('password123'),
                    'role'              => $role,
                    'email_verified_at' => now(),
                ]
            );
        }

        $adminUser = $users['admin'];

        // ── 2. Vendor user → Vendor record ────────────────────────────────────
        $vendor = Vendor::updateOrCreate(
            ['user_id' => $users['vendor']->id],
            [
                'business_name'            => 'Ravi Fresh Greens',
                'business_category'        => 'Organic Produce',
                'description'              => 'Daily fresh organic vegetables and fruits',
                'gstin'                    => '27AAACZ1234P1Z5',
                'pan'                      => 'AAACZ1234P',
                'trade_license_number'     => 'TL-2025-98761',
                'trade_license_expiry'     => now()->addYear(),
                'bank_account_number'      => '112233445566',
                'bank_ifsc_code'           => 'HDFC0001234',
                'bank_account_holder_name' => 'Ravi Kumar',
                'kyc_status'               => 'verified',
                'kyc_verified_at'          => now()->subDays(30),
                'kyc_verified_by'          => $adminUser->id,
                'address_line1'            => '12, Dadar Market',
                'city'                     => 'Mumbai',
                'state'                    => 'Maharashtra',
                'postal_code'              => '400014',
                'country'                  => 'India',
                'latitude'                 => 19.0176,
                'longitude'                => 72.8562,
            ]
        );

        VendorApproval::updateOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'approval_stage' => 'approved',
                'reviewed_by'    => $adminUser->id,
                'reviewed_at'    => now()->subDays(30),
                'submitted_at'   => now()->subDays(35),
            ]
        );

        // ── 3. Products for the vendor ────────────────────────────────────────
        $products = [
            ['Organic Tomatoes', 40, 80],
            ['Fresh Spinach',    30, 120],
            ['Desi Ghee 500ml',  280, 45],
            ['Brown Rice 1kg',   85, 200],
            ['Coconut Oil 1L',   180, 60],
        ];

        foreach ($products as [$name, $price, $stock]) {
            Product::updateOrCreate(
                ['vendor_id' => $vendor->id, 'name' => $name],
                [
                    'base_price'  => $price,
                    'description' => "Fresh $name",
                    'is_active'   => true,
                    'category'    => 'Groceries',
                    'sku'         => 'SKU-' . strtoupper(str_replace(' ', '-', $name)),
                ]
            );
        }

        // ── 4. Orders (last 7 days) ────────────────────────────────────────────
        $statuses = ['pending', 'confirmed', 'dispatched', 'delivered', 'delivered', 'delivered'];
        for ($i = 0; $i < 30; $i++) {
            $amount = rand(80, 800);
            Order::firstOrCreate(
                ['order_number' => 'ORD-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'vendor_id'              => $vendor->id,
                    'order_status'           => $statuses[array_rand($statuses)],
                    'subtotal'               => $amount,
                    'total_amount'           => $amount,
                    'delivery_address_line1' => '12, Dadar Market',
                    'delivery_city'          => 'Mumbai',
                    'delivery_state'         => 'Maharashtra',
                    'delivery_postal_code'   => '400014',
                    'delivery_country'       => 'India',
                    'delivery_phone'         => '9876543210',
                    'created_at'             => now()->subDays(rand(0, 6)),
                ]
            );
        }

        // ── 5. Dustbins & waste collections ───────────────────────────────────
        $dustbinData = [
            ['BIN-MUM-001', 'Mumbai', 'Dadar',       'dry',     87],
            ['BIN-MUM-002', 'Mumbai', 'Andheri',     'wet',     62],
            ['BIN-PUN-001', 'Pune',   'Kothrud',     'plastic', 94],
            ['BIN-BLR-001', 'Blore',  'Koramangala', 'mixed',   45],
            ['BIN-MUM-003', 'Mumbai', 'Bandra',      'e_waste', 78],
        ];

        foreach ($dustbinData as [$binId, $city, $area, $type, $fill]) {
            $bin = Dustbin::updateOrCreate(
                ['qr_code' => $binId],
                [
                    'bin_label'          => "$area $type bin",
                    'city'               => $city,
                    'area'               => $area,
                    'latitude'           => 19.0 + rand(-100, 100) / 1000,
                    'longitude'          => 72.8 + rand(-100, 100) / 1000,
                    'bin_type'           => $type,
                    'capacity_litres'    => 50,
                    'fill_level_percent' => $fill,
                    'status'             => $fill >= 75 ? 'full' : 'active',
                ]
            );

            WasteCollection::firstOrCreate(
                ['dustbin_id' => $bin->id, 'collection_number' => $binId . '-001'],
                [
                    'collected_by'    => $users['waste_management']->id,
                    'waste_weight_kg' => rand(20, 60),
                    'status'          => 'completed',
                    'scheduled_date'  => now()->subDays(rand(1, 3))->toDateString(),
                    'completed_at'    => now()->subDays(rand(0, 2)),
                ]
            );
        }

        // ── 6. Impact metrics (last 5 months) ──────────────────────────────────
        for ($m = 4; $m >= 0; $m--) {
            $date = now()->subMonths($m)->startOfMonth()->toDateString();
            ImpactMetric::updateOrCreate(
                ['metric_date' => $date],
                [
                    'plastic_avoided_kg'       => 3200 + ($m * 400),
                    'co2_saved_kg'             => 6800 + ($m * 440),
                    'recycling_rate_percent'   => 72 + ($m * 2.2),
                    'trees_saved_equivalent'   => 340 + ($m * 22),
                    'collections_completed'    => 120 + ($m * 5),
                    'dustbins_active'          => 260 + ($m * 6),
                ]
            );
        }

        // ── 7. Financial snapshots (last 5 days) ──────────────────────────────
        for ($d = 4; $d >= 0; $d--) {
            FinancialDailySnapshot::updateOrCreate(
                ['snapshot_date' => now()->subDays($d)->toDateString()],
                [
                    'total_revenue'        => 110000 + rand(-15000, 25000),
                    'daily_burn_rate'      => 80000 + rand(-3000, 5000),
                    'cash_balance'         => 11500000,
                    'runway_days'          => 430,
                    'gross_margin_percent' => 34.2,
                    'cac'                  => 340 + rand(-20, 20),
                    'ltv'                  => 2840 + rand(-100, 100),
                    'subscription_revenue' => 58000,
                    'product_revenue'      => 284000,
                ]
            );
        }

        // ── 8. Campaigns ───────────────────────────────────────────────────────
        $campaignData = [
            ['Summer Eco Push',    'social_media', 'active',  80000, 52000, 180000, 12400, 840],
            ['Referral Blast',     'referral',     'active',  40000, 28000, 0,      0,     320],
            ['Onboard SMS Drive',  'sms',          'paused',  20000, 18000, 40000,  3200,  210],
            ['App Push Rewards',   'push',         'active',  15000, 9000,  62000,  8100,  540],
        ];

        foreach ($campaignData as [$name, $type, $status, $budget, $spent, $impr, $clicks, $conv]) {
            Campaign::updateOrCreate(
                ['name' => $name],
                [
                    'type'         => $type,
                    'status'       => $status,
                    'budget'       => $budget,
                    'amount_spent' => $spent,
                    'impressions'  => $impr,
                    'clicks'       => $clicks,
                    'conversions'  => $conv,
                    'start_date'   => now()->subDays(30)->toDateString(),
                    'end_date'     => now()->addDays(30)->toDateString(),
                    'created_by'   => $users['marketing']->id,
                ]
            );
        }

        // ── 9. City settings ───────────────────────────────────────────────────
        $cityData = [
            ['Mumbai',    'active',   142, '2025-01-01'],
            ['Pune',      'active',   89,  '2025-03-01'],
            ['Bangalore', 'active',   67,  '2025-04-01'],
            ['Delhi',     'pilot',    28,  '2025-04-15'],
            ['Hyderabad', 'planning', 0,   '2025-09-01'],
        ];

        foreach ($cityData as [$city, $status, $vendors, $launch]) {
            CitySettings::updateOrCreate(
                ['city' => $city],
                [
                    'state'            => 'Maharashtra',
                    'status'           => $status,
                    'target_vendors'   => $vendors,
                    'launch_date'      => $launch,
                    'delivery_charge'  => 20,
                    'min_order_value'  => 50,
                ]
            );
        }

        // ── 10. Alerts ─────────────────────────────────────────────────────────
        $alertData = [
            ['SLA Breach',     'critical', 'delivery_delay',  '8 orders in Delhi exceed SLA threshold'],
            ['Low Stock',      'warning',  'low_stock',       '14 vendors below reorder level'],
            ['KYC Pending',    'info',     'kyc_pending',     '4 vendors awaiting KYC review'],
            ['Payment Failed', 'warning',  'payment_failure', '3 payment failures in the last hour'],
        ];

        foreach ($alertData as [$type, $severity, $alertType, $message]) {
            Alert::firstOrCreate(
                ['title' => $type, 'alert_type' => $alertType],
                [
                    'severity'    => $severity,
                    'message'     => $message,
                    'is_resolved' => false,
                ]
            );
        }

        // ── 11. Reward catalog ─────────────────────────────────────────────────
        RewardCatalog::firstOrCreate(
            ['name' => 'Free Delivery Voucher'],
            ['points_required' => 100, 'reward_type' => 'discount_coupon', 'is_active' => true]
        );
        RewardCatalog::firstOrCreate(
            ['name' => '₹50 Cashback'],
            ['points_required' => 200, 'reward_type' => 'cashback', 'is_active' => true]
        );

        // ── 12. Consumer reward transactions ──────────────────────────────────
        $rewards = RewardCatalog::all();
        foreach ($rewards as $reward) {
            RewardTransaction::firstOrCreate(
                ['user_id' => $users['consumer']->id, 'reward_catalog_id' => $reward->id],
                [
                    'points'               => $reward->points_required,
                    'transaction_type'     => 'redeem',
                    'points_balance_after' => 0,
                    'source'               => 'redemption',
                    'description'          => 'Redeemed ' . $reward->name,
                ]
            );
        }

        // ── 13. Consumer subscription ──────────────────────────────────────────
        Subscription::updateOrCreate(
            ['user_id' => $users['consumer']->id],
            [
                'plan_name'          => 'Eco Weekly Box',
                'status'             => 'active',
                'plan_price'         => 349,
                'frequency'          => 'weekly',
                'next_delivery_date' => now()->addDays(4)->toDateString(),
                'vendor_id'          => $vendor->id,
                'start_date'         => now()->toDateString(),
            ]
        );

        $this->command->info('✅ RoleDemoSeeder complete.');
        $this->command->line('');
        $this->command->info('Demo credentials (password: password123):');
        foreach ($this->demoUsers as $role => $attrs) {
            $this->command->line("  [{$role}] {$attrs['email']}");
        }
    }
}
