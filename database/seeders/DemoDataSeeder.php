<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApproval;
use App\Models\VendorQrCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Payment;
use App\Models\Cart;
use App\Models\CartItem;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo users
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@ourth.local'],
            [
                'name' => 'Admin Officer',
                'password' => bcrypt('password123'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'government@ourth.local'],
            [
                'name' => 'Government Officer',
                'password' => bcrypt('password123'),
                'role' => 'government',
                'email_verified_at' => now(),
            ]
        );

        // Create demo vendor users and vendors with approvals
        $vendorData = [
            ['Kumar Electronics', 'Rajesh Kumar', 'rajesh@kumar-electronics.com', 'approved', 'verified'],
            ['Sharma Fashion', 'Priya Sharma', 'priya@sharma-fashion.com', 'under_review', 'under_review'],
            ['Patel Hardware', 'Vikram Patel', 'vikram@patel-hardware.com', 'documents_submitted', 'under_review'],
            ['Singh Retail', 'Amandeep Singh', 'amandeep@singh-retail.com', 'rejected', 'rejected'],
            ['Gupta Foods', 'Anuj Gupta', 'anuj@gupta-foods.com', 'approved', 'verified'],
            ['Verma Trading', 'Sanjay Verma', 'sanjay@verma-trading.com', 'approved', 'verified'],
            ['Desai Exports', 'Divya Desai', 'divya@desai-exports.com', 'approved', 'verified'],
            ['Iyer Logistics', 'Karthik Iyer', 'karthik@iyer-logistics.com', 'pending_documents', 'under_review'],
        ];

        foreach ($vendorData as [$businessName, $ownerName, $email, $approvalStage, $kycStatus]) {
            // Create vendor user
            $vendorUser = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $ownerName,
                    'password' => bcrypt('password123'),
                    'role' => 'vendor',
                    'email_verified_at' => now(),
                ]
            );

            // Create vendor
            $vendor = Vendor::updateOrCreate(
                ['user_id' => $vendorUser->id],
                [
                    'business_name' => $businessName,
                    'business_category' => collect(['Electronics', 'Fashion', 'Hardware', 'Retail', 'Foods', 'Trading', 'Exports', 'Logistics'])->random(),
                    'description' => "Demo vendor: $businessName",
                    'gstin' => '27AAA' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                    'pan' => 'AAAA' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT) . 'X',
                    'trade_license_number' => 'TL-' . date('Y') . '-' . rand(10000, 99999),
                    'trade_license_expiry' => now()->addYears(1),
                    'bank_account_number' => str_pad(rand(0, 999999999999), 12, '0', STR_PAD_LEFT),
                    'bank_ifsc_code' => 'DEMO0000001',
                    'bank_account_holder_name' => $ownerName,
                    'kyc_status' => $kycStatus,
                    'kyc_verified_at' => $kycStatus === 'verified' ? now() : null,
                    'kyc_verified_by' => $kycStatus === 'verified' ? $adminUser->id : null,
                    'address_line1' => '123 Business Street',
                    'address_line2' => 'Commercial Building',
                    'city' => collect(['Mumbai', 'Delhi', 'Bangalore', 'Pune', 'Chennai'])->random(),
                    'state' => collect(['Maharashtra', 'Delhi', 'Karnataka', 'Tamil Nadu'])->random(),
                    'postal_code' => str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                    'country' => 'India',
                    'latitude' => 28.7041 + (rand(-1000, 1000) / 1000),
                    'longitude' => 77.1025 + (rand(-1000, 1000) / 1000),
                ]
            );

            // Create vendor approval record
            VendorApproval::updateOrCreate(
                ['vendor_id' => $vendor->id],
                [
                    'approval_stage' => $approvalStage,
                    'reviewed_by' => in_array($approvalStage, ['approved', 'rejected']) ? $adminUser->id : null,
                    'reviewed_at' => in_array($approvalStage, ['approved', 'rejected']) ? now() : null,
                    'submitted_at' => now()->subDays(rand(1, 30)),
                    'approval_notes' => $approvalStage === 'approved' ? 'Demo vendor approved' : null,
                    'rejection_reason' => $approvalStage === 'rejected' ? 'Demo rejection for testing' : null,
                ]
            );
        }

        echo "\n✅ Demo data seeded successfully!\n";
        echo "   - 8 Vendors created (3 approved, 2 pending, 2 under review, 1 rejected)\n";
        echo "   - Admin + Government users ready\n";
        echo "   - Backend is now ready for API connections\n";
    }
}
