<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DashboardDemoSeeder
 *
 * Seeds realistic demo data for all 8 role dashboards:
 * founder, vendor, consumer, operations, waste_management, finance, admin, marketing
 */
class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();

        $this->seedInventory();
        $this->seedOrderItems();
        $this->seedVendorDailyStats($today);
        $this->seedDeliveries($today);
        $this->seedDeliveryRoutes($today);
        $this->seedWarehouses();
        $this->seedSustainabilityScores();
        $this->seedWasteSegregationLogs();
        $this->seedAdditionalAlerts();

        $this->command->info('DashboardDemoSeeder completed.');
    }

    // ── 1. INVENTORY ─────────────────────────────────────────────────────────
    // Products belong to vendors 2, 6, 7, 8, 10. Each product_id is unique.
    private function seedInventory(): void
    {
        // Products 22-26 belong to vendor 10 (Ravi Fresh Greens — the demo vendor)
        $items = [
            // product_id, vendor_id, current_stock, reserved, min_level, reorder_qty
            [22, 10, 120,  5, 20, 50],
            [23, 10,   8,  2, 15, 40],  // low stock — triggers alert
            [24, 10,  55,  3, 10, 30],
            [25, 10,   6,  1, 10, 50],  // low stock
            [26, 10,  80,  4, 15, 40],
            // Products 1-5 belong to vendor 2 (Kumar Electronics)
            [1,  2, 200, 10, 30, 100],
            [2,  2, 150,  8, 25,  80],
            [3,  2,  12,  2, 20,  60],  // low stock
            [4,  2,  90,  5, 15,  50],
            [5,  2,  75,  3, 10,  30],
            // Products 6-10 belong to vendor 6 (Gupta Foods)
            [6,  6, 300, 15, 40, 120],
            [7,  6, 220, 12, 30,  90],
            [8,  6,   9,  1, 20,  70],  // low stock
            [9,  6, 180,  8, 25,  80],
            [10, 6, 140,  6, 20,  60],
            // Products 11-15 belong to vendor 7 (Verma Trading)
            [11, 7, 260, 14, 35, 100],
            [12, 7, 190, 10, 28,  80],
            [13, 7,  14,  2, 20,  60],  // low stock
            [14, 7, 170,  7, 25,  70],
            [15, 7, 130,  5, 18,  50],
            // Products 16-20 belong to vendor 8 (Desai Exports)
            [16, 8, 310, 18, 40, 120],
            [17, 8, 240, 13, 32,  90],
            [18, 8,  11,  1, 20,  65],  // low stock
            [19, 8, 195, 10, 28,  80],
            [20, 8, 160,  7, 22,  60],
        ];

        $now = now();
        $rows = [];
        foreach ($items as [$productId, $vendorId, $stock, $reserved, $minLevel, $reorder]) {
            $rows[] = [
                'product_id' => $productId,
                'vendor_id' => $vendorId,
                'current_stock' => $stock,
                'reserved_stock' => $reserved,
                'minimum_stock_level' => $minLevel,
                'reorder_quantity' => $reorder,
                'last_restocked_at' => $now->subDays(rand(1, 14)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('inventory')->upsert(
            $rows,
            ['product_id'],
            ['current_stock', 'reserved_stock', 'minimum_stock_level', 'reorder_quantity', 'updated_at']
        );
    }

    // ── 2. ORDER ITEMS ───────────────────────────────────────────────────────
    // All 30 orders belong to vendor 10, products 22-26.
    private function seedOrderItems(): void
    {
        // Skip if already seeded
        if (DB::table('order_items')->exists()) {
            return;
        }

        $orders = DB::table('orders')->get(['id', 'total_amount']);
        $products = DB::table('products')
            ->where('vendor_id', 10)
            ->get(['id', 'name', 'base_price']);

        $productList = $products->toArray();
        $now = now();

        foreach ($orders as $order) {
            $count = rand(1, 3);
            $used = [];
            for ($i = 0; $i < $count; $i++) {
                $prod = $productList[array_rand($productList)];
                if (in_array($prod->id, $used)) {
                    continue;
                }
                $used[] = $prod->id;
                $qty = rand(1, 4);
                $unitPrice = (float) ($prod->base_price ?? rand(50, 400));
                $total = round($unitPrice * $qty, 2);

                DB::table('order_items')->insert([
                    'order_id' => $order->id,
                    'product_id' => $prod->id,
                    'product_name' => $prod->name,
                    'product_sku' => 'SKU-'.str_pad($prod->id, 4, '0', STR_PAD_LEFT),
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $total,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    // ── 3. VENDOR DAILY STATS ────────────────────────────────────────────────
    // 30 days of stats for all 10 vendors.
    private function seedVendorDailyStats(Carbon $today): void
    {
        $vendorIds = DB::table('vendors')->pluck('id');

        // Revenue profiles per vendor (base daily revenue)
        $profiles = [
            10 => ['revenue' => 8500,  'orders' => 22],   // demo vendor
            1 => ['revenue' => 12000, 'orders' => 30],
            2 => ['revenue' => 15000, 'orders' => 38],
            3 => ['revenue' => 9000,  'orders' => 24],
            4 => ['revenue' => 7500,  'orders' => 20],
            5 => ['revenue' => 6000,  'orders' => 16],
            6 => ['revenue' => 18000, 'orders' => 45],
            7 => ['revenue' => 11000, 'orders' => 28],
            8 => ['revenue' => 13500, 'orders' => 34],
            9 => ['revenue' => 5000,  'orders' => 13],
        ];

        $rows = [];
        foreach ($vendorIds as $vendorId) {
            $profile = $profiles[$vendorId] ?? ['revenue' => 5000, 'orders' => 13];

            for ($d = 30; $d >= 0; $d--) {
                $date = $today->copy()->subDays($d);

                // Weekend bump, weekday slight dip
                $factor = in_array($date->dayOfWeek, [0, 6]) ? 1.25 : 1.0;
                $factor *= (0.85 + mt_rand(0, 30) / 100); // ±15% random
                $orders = (int) round($profile['orders'] * $factor);
                $revenue = round($profile['revenue'] * $factor, 2);
                $delivered = (int) round($orders * 0.75);
                $cancelled = (int) round($orders * 0.06);
                $returned = (int) round($orders * 0.02);
                $aov = $orders > 0 ? round($revenue / $orders, 2) : 0;
                $unique = (int) round($orders * 0.90);

                $rows[] = [
                    'vendor_id' => $vendorId,
                    'stats_date' => $date->toDateString(),
                    'total_orders' => $orders,
                    'total_revenue' => $revenue,
                    'delivered_orders' => $delivered,
                    'cancelled_orders' => $cancelled,
                    'returned_orders' => $returned,
                    'average_order_value' => $aov,
                    'unique_customers' => $unique,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Upsert to avoid duplicates on re-run
        DB::table('vendor_daily_stats')->upsert(
            $rows,
            ['vendor_id', 'stats_date'],
            ['total_orders', 'total_revenue', 'delivered_orders', 'cancelled_orders',
                'returned_orders', 'average_order_value', 'unique_customers', 'updated_at']
        );
    }

    // ── 4. DELIVERIES ────────────────────────────────────────────────────────
    // Create deliveries for all existing orders.
    private function seedDeliveries(Carbon $today): void
    {
        if (DB::table('deliveries')->exists()) {
            return;
        }

        // Use operations user (id=16) as delivery partner
        $partnerId = 16;
        $orders = DB::table('orders')->get(['id', 'order_status', 'created_at']);
        $now = now();

        $rows = [];
        foreach ($orders as $order) {
            $status = match ($order->order_status) {
                'delivered' => 'delivered',
                'dispatched' => 'in_transit',
                'confirmed' => 'assigned',
                'pending' => 'pending',
                default => 'pending',
            };

            $assignedAt = null;
            $pickedUpAt = null;
            $deliveredAt = null;

            if (in_array($status, ['assigned', 'in_transit', 'delivered'])) {
                $assignedAt = Carbon::parse($order->created_at)->addHours(2);
            }
            if (in_array($status, ['in_transit', 'delivered'])) {
                $pickedUpAt = $assignedAt->copy()->addMinutes(30);
            }
            if ($status === 'delivered') {
                $deliveredAt = $pickedUpAt->copy()->addMinutes(rand(45, 120));
            }

            $rows[] = [
                'order_id' => $order->id,
                'delivery_partner_id' => in_array($status, ['pending']) ? null : $partnerId,
                'delivery_status' => $status,
                'assigned_at' => $assignedAt,
                'picked_up_at' => $pickedUpAt,
                'delivered_at' => $deliveredAt,
                'distance_km' => rand(2, 18) + (rand(0, 9) / 10),
                'estimated_time_minutes' => rand(30, 90),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('deliveries')->insert($rows);
    }

    // ── 5. DELIVERY ROUTES ───────────────────────────────────────────────────
    // Active routes for today (operations dashboard).
    private function seedDeliveryRoutes(Carbon $today): void
    {
        if (DB::table('delivery_routes')->exists()) {
            return;
        }

        $partnerId = 16; // operations user
        $routes = [
            ['RTE-MUM-001', 'Mumbai',    'in_progress', 12, 7,  43.5, 180, 195],
            ['RTE-MUM-002', 'Mumbai',    'planned',      9, 0,  31.2, 150, null],
            ['RTE-DEL-001', 'Delhi',     'in_progress', 15, 9,  55.0, 210, 220],
            ['RTE-PUN-001', 'Pune',      'planned',      8, 0,  28.0, 130, null],
            ['RTE-BLR-001', 'Bangalore', 'in_progress', 11, 5,  38.0, 160, 170],
        ];

        $now = now();
        foreach ($routes as [$num, $city, $status, $stops, $done, $dist, $est, $actual]) {
            DB::table('delivery_routes')->insert([
                'route_number' => $num,
                'route_date' => $today->toDateString(),
                'city' => $city,
                'delivery_partner_id' => $partnerId,
                'status' => $status,
                'total_stops' => $stops,
                'completed_stops' => $done,
                'total_distance_km' => $dist,
                'estimated_duration_minutes' => $est,
                'actual_duration_minutes' => $actual,
                'started_at' => $status === 'in_progress' ? $now->copy()->subHours(2) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    // ── 6. WAREHOUSES + WAREHOUSE INVENTORY ─────────────────────────────────
    private function seedWarehouses(): void
    {
        if (DB::table('warehouses')->exists()) {
            return;
        }

        $warehouseData = [
            ['Ourth Mumbai Hub',    'WH-MUM-01', 'Mumbai',    'Lower Parel, Mumbai',          19.0045, 72.8259, 5000],
            ['Ourth Delhi Depot',   'WH-DEL-01', 'Delhi',     'Okhla Phase II, New Delhi',    28.5355, 77.2563, 4000],
            ['Ourth Bangalore DC',  'WH-BLR-01', 'Bangalore', 'Electronic City, Bangalore',   12.8399, 77.6770, 3500],
        ];

        $now = now();
        foreach ($warehouseData as [$name, $code, $city, $address, $lat, $lng, $cap]) {
            $id = DB::table('warehouses')->insertGetId([
                'name' => $name,
                'code' => $code,
                'city' => $city,
                'address' => $address,
                'latitude' => $lat,
                'longitude' => $lng,
                'status' => 'active',
                'total_capacity_units' => $cap,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Seed warehouse_inventory for a few products each
            $products = DB::table('products')->inRandomOrder()->limit(8)->get(['id']);
            $usedProducts = [];
            foreach ($products as $prod) {
                if (in_array($prod->id, $usedProducts)) {
                    continue;
                }
                $usedProducts[] = $prod->id;
                $onHand = rand(50, 500);
                $reserved = rand(5, 50);
                $avail = max(0, $onHand - $reserved);
                DB::table('warehouse_inventory')->insertOrIgnore([
                    'warehouse_id' => $id,
                    'product_id' => $prod->id,
                    'quantity_on_hand' => $onHand,
                    'quantity_reserved' => $reserved,
                    'quantity_available' => $avail,
                    'reorder_level' => rand(20, 60),
                    'reorder_quantity' => rand(50, 200),
                    'last_updated_at' => $now->copy()->subHours(rand(1, 48)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    // ── 7. SUSTAINABILITY SCORES ─────────────────────────────────────────────
    private function seedSustainabilityScores(): void
    {
        // Consumer user id = 15
        DB::table('sustainability_scores')->upsert([
            [
                'user_id' => 15,
                'green_points' => 1240,
                'carbon_points' => 860,
                'total_points' => 2100,
                'tier' => 'silver',
                'plastic_avoided_kg' => 12.40,
                'co2_saved_kg' => 18.75,
                'eco_orders_count' => 34,
                'bins_used_count' => 18,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['user_id'], ['green_points', 'carbon_points', 'total_points', 'tier',
            'plastic_avoided_kg', 'co2_saved_kg', 'eco_orders_count', 'bins_used_count', 'updated_at']);

        // Add scores for a few other consumer-type users so the admin sees numbers
        $otherUsers = DB::table('users')
            ->whereIn('id', [1, 2, 15])
            ->pluck('id');

        foreach ($otherUsers as $uid) {
            DB::table('sustainability_scores')->upsert([
                [
                    'user_id' => $uid,
                    'green_points' => rand(200, 2000),
                    'carbon_points' => rand(100, 1500),
                    'total_points' => rand(400, 3500),
                    'tier' => collect(['bronze', 'silver', 'gold'])->random(),
                    'plastic_avoided_kg' => round(rand(1, 30) + rand(0, 9) / 10, 2),
                    'co2_saved_kg' => round(rand(2, 50) + rand(0, 9) / 10, 2),
                    'eco_orders_count' => rand(5, 80),
                    'bins_used_count' => rand(2, 40),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ], ['user_id'], ['green_points', 'carbon_points', 'total_points', 'tier',
                'plastic_avoided_kg', 'co2_saved_kg', 'eco_orders_count', 'bins_used_count', 'updated_at']);
        }
    }

    // ── 8. WASTE SEGREGATION LOGS ────────────────────────────────────────────
    // For existing waste_collection ids 2-6.
    private function seedWasteSegregationLogs(): void
    {
        if (DB::table('waste_segregation_logs')->exists()) {
            return;
        }

        $collectionIds = DB::table('waste_collections')->pluck('id');
        $wasteUserId = 17; // waste@ourth.local

        $profiles = [
            ['dry' => 18, 'wet' => 12, 'plastic' => 14, 'ewaste' => 3, 'hazardous' => 1, 'other' => 7],
            ['dry' => 14, 'wet' => 20, 'plastic' => 10, 'ewaste' => 1, 'hazardous' => 0, 'other' => 6],
            ['dry' => 10, 'wet' => 8, 'plastic' => 22, 'ewaste' => 2, 'hazardous' => 1, 'other' => 7],
            ['dry' => 16, 'wet' => 10, 'plastic' => 8, 'ewaste' => 0, 'hazardous' => 0, 'other' => 4],
            ['dry' => 12, 'wet' => 9, 'plastic' => 9, 'ewaste' => 5, 'hazardous' => 2, 'other' => 4],
        ];

        $now = now();
        foreach ($collectionIds as $i => $collectionId) {
            $p = $profiles[$i % count($profiles)];
            DB::table('waste_segregation_logs')->insert([
                'waste_collection_id' => $collectionId,
                'dry_waste_kg' => $p['dry'],
                'wet_waste_kg' => $p['wet'],
                'plastic_waste_kg' => $p['plastic'],
                'e_waste_kg' => $p['ewaste'],
                'hazardous_waste_kg' => $p['hazardous'],
                'other_waste_kg' => $p['other'],
                'logged_by' => $wasteUserId,
                'notes' => 'Routine segregation log',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    // ── 9. ADDITIONAL ALERTS ─────────────────────────────────────────────────
    private function seedAdditionalAlerts(): void
    {
        $now = now();
        $extra = [
            ['low_stock',       'critical', 'Bins Near Full',    '3 dustbins in Mumbai at >90% capacity',         'Mumbai'],
            ['delivery_delay',  'warning',  'Revenue Dip Alert', 'Today revenue 12% below weekly average',        'Delhi'],
            ['kyc_pending',     'info',     'New Vendor KYC',    'Ravi Fresh Greens cleared KYC verification',    'Mumbai'],
            ['stock_out',       'critical', 'Overflow Risk',     'BIN-PUN-001 at 94% — schedule urgent pickup',   'Pune'],
        ];

        foreach ($extra as [$type, $severity, $title, $message, $city]) {
            $exists = DB::table('alerts')
                ->where('alert_type', $type)
                ->where('title', $title)
                ->exists();
            if (! $exists) {
                DB::table('alerts')->insert([
                    'alert_type' => $type,
                    'severity' => $severity,
                    'title' => $title,
                    'message' => $message,
                    'city' => $city,
                    'is_resolved' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
