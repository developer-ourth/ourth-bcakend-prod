// ============================================================================
// OURTH APP - UPDATED MIGRATIONS FOR WORKFLOW REQUIREMENTS
// ============================================================================
// Based on Workflow Document: Vendor Onboarding → Order Management → Delivery
// Additional tables for: Cart, Vendor Scoring, Blockchain, Carbon Tracking, Loyalty
// ============================================================================

// ============================================================================
// 1. CART SYSTEM (For Vendor Ordering)
// ============================================================================

// database/migrations/2024_01_01_000020_create_carts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            
            // Cart metadata
            $table->decimal('total_items_price', 12, 2)->default(0);
            $table->integer('total_items_count')->default(0);
            $table->enum('cart_status', ['active', 'abandoned', 'converted_to_order'])
                  ->default('active');
            
            // Last activity timestamp (for abandonment tracking)
            $table->timestamp('last_activity_at')->useCurrent();
            
            $table->timestamps();
            
            // Indexes
            $table->index('vendor_id');
            $table->index('cart_status');
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('carts');
    }
};

// database/migrations/2024_01_01_000021_create_cart_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            
            // Item details
            $table->string('product_name');
            $table->string('product_sku', 100)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->integer('quantity');
            $table->decimal('item_total', 12, 2);
            
            $table->timestamps();
            
            // Indexes
            $table->index('cart_id');
            $table->index('product_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('cart_items');
    }
};

// ============================================================================
// 2. VENDOR ADMIN APPROVAL WORKFLOW
// ============================================================================

// database/migrations/2024_01_01_000022_create_vendor_approval_workflow_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vendor_approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            
            // Approval workflow stages
            $table->enum('approval_stage', [
                'pending_documents',      // Waiting for KYC docs
                'documents_submitted',    // Docs received, awaiting review
                'under_review',          // Admin reviewing
                'address_verification',  // Physical address verification pending
                'approved',              // Vendor approved and active
                'rejected'               // Vendor rejected
            ])->default('pending_documents');
            
            // Reviewer tracking
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullifyOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            
            $table->foreignId('address_verified_by')->nullable()->constrained('users')->nullifyOnDelete();
            $table->timestamp('address_verified_at')->nullable();
            
            // Rejection reason (if applicable)
            $table->text('rejection_reason')->nullable();
            $table->text('rejection_notes')->nullable();
            
            // Approval notes
            $table->text('approval_notes')->nullable();
            
            // Timeline
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            // Indexes
            $table->index('vendor_id');
            $table->index('approval_stage');
            $table->index('created_at');
            $table->unique('vendor_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('vendor_approvals');
    }
};

// ============================================================================
// 3. DISPATCH SLIP MANAGEMENT
// ============================================================================

// database/migrations/2024_01_01_000023_create_dispatch_slips_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('dispatch_slips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            
            // Dispatch tracking
            $table->string('dispatch_slip_number', 50)->unique();
            $table->enum('dispatch_status', [
                'pending',       // Waiting for warehouse approval
                'approved',      // Approved for dispatch
                'packed',        // Items packed and ready
                'handed_over',   // Handed to logistics
                'in_transit',    // In transit
                'failed'         // Dispatch failed
            ])->default('pending');
            
            // Warehouse info
            $table->text('packing_details')->nullable(); // JSON - what was packed
            $table->string('warehouse_location', 255)->nullable();
            
            // Logistics info
            $table->string('logistics_partner', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('vehicle_details', 255)->nullable(); // Vehicle plate number, etc
            
            // Handed over by
            $table->foreignId('packed_by')->nullable()->constrained('users')->nullifyOnDelete();
            $table->timestamp('packed_at')->nullable();
            
            $table->foreignId('handed_over_by')->nullable()->constrained('users')->nullifyOnDelete();
            $table->timestamp('handed_over_at')->nullable();
            
            // Expected delivery
            $table->timestamp('expected_delivery_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('order_id');
            $table->index('dispatch_status');
            $table->index('tracking_number');
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('dispatch_slips');
    }
};

// ============================================================================
// 4. DELIVERY OTP & QR VERIFICATION
// ============================================================================

// database/migrations/2024_01_01_000024_create_delivery_verification_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('delivery_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            
            // OTP Verification
            $table->string('delivery_otp', 6)->nullable();
            $table->timestamp('otp_sent_at')->nullable();
            $table->integer('otp_attempts')->default(0);
            $table->boolean('otp_verified')->default(false);
            $table->timestamp('otp_verified_at')->nullable();
            
            // QR Code Verification
            $table->string('vendor_qr_code_id', 255)->nullable();
            $table->boolean('qr_verified')->default(false);
            $table->timestamp('qr_verified_at')->nullable();
            
            // Photo/Signature
            $table->string('signature_photo_url', 500)->nullable();
            $table->string('delivery_photo_url', 500)->nullable();
            
            // Recipient details
            $table->string('recipient_name', 255)->nullable();
            $table->string('recipient_phone', 15)->nullable();
            
            // Verification method used
            $table->enum('verification_method', ['otp', 'qr_code', 'signature', 'photo', 'mixed'])->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('delivery_id');
            $table->index('otp_verified');
            $table->index('qr_verified');
        });
    }

    public function down(): void {
        Schema::dropIfExists('delivery_verifications');
    }
};

// ============================================================================
// 5. VENDOR SCORING SYSTEM (AI-Based)
// ============================================================================

// database/migrations/2024_01_01_000025_create_vendor_scores_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vendor_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained()->cascadeOnDelete();
            
            // Overall Score (0-100)
            $table->integer('overall_score')->default(0);
            
            // Component Scores
            $table->integer('compliance_score')->default(0);    // GST, KYC compliance
            $table->integer('delivery_score')->default(0);      // On-time delivery
            $table->integer('quality_score')->default(0);       // Product quality
            $table->integer('reliability_score')->default(0);   // Repeat orders, consistency
            $table->integer('sustainability_score')->default(0); // Carbon footprint, eco-friendly
            
            // Metrics
            $table->integer('on_time_delivery_percentage')->default(0);
            $table->integer('order_fulfillment_percentage')->default(0);
            $table->decimal('average_rating', 2, 1)->default(0);
            $table->integer('total_successful_orders')->default(0);
            $table->integer('total_cancelled_orders')->default(0);
            $table->integer('total_returns')->default(0);
            
            // Carbon metrics
            $table->decimal('total_carbon_emissions_kg', 10, 2)->default(0);
            $table->decimal('avg_carbon_per_order_kg', 10, 2)->default(0);
            
            // Grade (A, B, C, D, F)
            $table->enum('grade', ['A', 'B', 'C', 'D', 'F'])->nullable();
            
            // Last calculated
            $table->timestamp('calculated_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('vendor_id');
            $table->index('overall_score');
            $table->index('grade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('vendor_scores');
    }
};

// ============================================================================
// 6. BLOCKCHAIN VERIFICATION (For Order Authenticity)
// ============================================================================

// database/migrations/2024_01_01_000026_create_blockchain_verifications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('blockchain_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Verifiable entity (polymorphic)
            $table->string('verifiable_type'); // 'order', 'product', 'vendor'
            $table->unsignedBigInteger('verifiable_id');
            
            // Blockchain details
            $table->string('blockchain_hash', 255)->unique(); // Hash on blockchain
            $table->string('blockchain_network', 100); // ethereum, polygon, hyperledger, etc
            $table->string('transaction_id', 255)->nullable();
            $table->string('block_number', 255)->nullable();
            
            // Verification status
            $table->enum('status', ['pending', 'recorded', 'verified', 'failed'])->default('pending');
            $table->text('verification_data')->nullable(); // JSON - what was verified
            
            // Timestamps
            $table->timestamp('recorded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['verifiable_type', 'verifiable_id']);
            $table->index('blockchain_hash');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('blockchain_verifications');
    }
};

// ============================================================================
// 7. CARBON TRACKING DASHBOARD
// ============================================================================

// database/migrations/2024_01_01_000027_create_carbon_emissions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('carbon_emissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Emission source (polymorphic)
            $table->string('source_type'); // 'delivery', 'order_fulfillment', 'vendor'
            $table->unsignedBigInteger('source_id');
            
            // Vendor and Order context
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullifyOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained()->nullifyOnDelete();
            
            // Emission details
            $table->enum('emission_category', [
                'transportation',      // Delivery vehicle emissions
                'packaging',          // Packaging material carbon
                'warehouse',          // Warehouse operations
                'product_lifecycle',  // Manufacturing/production
                'returns'             // Return shipments
            ]);
            
            // Metrics
            $table->decimal('distance_km', 10, 2)->nullable(); // For delivery
            $table->enum('vehicle_type', ['bike', 'auto', 'van', 'truck', 'electric'])->nullable();
            $table->decimal('fuel_liters', 8, 2)->nullable();
            
            // Carbon calculation
            $table->decimal('carbon_kg', 10, 2); // CO2 equivalent in kg
            $table->string('calculation_method', 100); // How it was calculated
            
            // Offset info
            $table->boolean('offset_purchased')->default(false);
            $table->decimal('offset_amount_kg', 10, 2)->nullable();
            $table->string('offset_partner', 100)->nullable(); // Carbon offset provider
            
            // Timestamp
            $table->timestamp('emission_date')->useCurrent();
            $table->timestamps();
            
            // Indexes
            $table->index(['vendor_id', 'emission_date']);
            $table->index(['source_type', 'source_id']);
            $table->index('emission_category');
            $table->index('emission_date');
        });
    }

    public function down(): void {
        Schema::dropIfExists('carbon_emissions');
    }
};

// database/migrations/2024_01_01_000028_create_carbon_analytics_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('carbon_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            
            // Time period
            $table->date('analytics_date');
            $table->enum('period_type', ['daily', 'weekly', 'monthly', 'yearly'])->default('daily');
            
            // Aggregated metrics
            $table->decimal('total_emissions_kg', 10, 2)->default(0);
            $table->integer('total_orders', 5)->default(0);
            $table->decimal('emissions_per_order_kg', 10, 2)->default(0);
            
            // Breakdown
            $table->decimal('transport_emissions_kg', 10, 2)->default(0);
            $table->decimal('packaging_emissions_kg', 10, 2)->default(0);
            $table->decimal('warehouse_emissions_kg', 10, 2)->default(0);
            $table->decimal('returns_emissions_kg', 10, 2)->default(0);
            
            // Goals & reduction
            $table->decimal('target_emissions_kg', 10, 2)->nullable();
            $table->decimal('emission_reduction_percentage', 5, 2)->default(0);
            
            $table->timestamps();
            
            // Unique per vendor per date
            $table->unique(['vendor_id', 'analytics_date']);
            
            // Indexes
            $table->index(['vendor_id', 'analytics_date']);
            $table->index('analytics_date');
        });
    }

    public function down(): void {
        Schema::dropIfExists('carbon_analytics');
    }
};

// ============================================================================
// 8. VENDOR LOYALTY & REWARD POINTS SYSTEM
// ============================================================================

// database/migrations/2024_01_01_000029_create_vendor_loyalty_accounts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vendor_loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained()->cascadeOnDelete();
            
            // Points tracking
            $table->bigInteger('total_points_earned')->default(0);
            $table->bigInteger('available_points')->default(0);
            $table->bigInteger('redeemed_points')->default(0);
            
            // Tier system
            $table->enum('loyalty_tier', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze');
            $table->bigInteger('points_in_current_tier')->default(0);
            
            // Benefits
            $table->integer('discount_percentage')->default(0); // Tier-based discount
            $table->boolean('priority_support')->default(false);
            $table->boolean('early_access_features')->default(false);
            
            // Metrics for tier upgrades
            $table->integer('successful_orders_count')->default(0);
            $table->decimal('total_order_value', 15, 2)->default(0);
            $table->integer('months_active')->default(0);
            
            // Last activity
            $table->timestamp('last_activity_at')->useCurrent();
            
            $table->timestamps();
            
            // Indexes
            $table->index('loyalty_tier');
            $table->index('available_points');
        });
    }

    public function down(): void {
        Schema::dropIfExists('vendor_loyalty_accounts');
    }
};

// database/migrations/2024_01_01_000030_create_loyalty_points_ledger_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('loyalty_points_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            
            // Transaction type
            $table->enum('transaction_type', [
                'order_placed',          // Points for placing order
                'order_completed',       // Points for successful completion
                'referral',              // Referral bonus
                'review_submitted',      // Points for review
                'milestone_achieved',    // Milestone bonus
                'redemption',            // Points redeemed
                'tier_upgrade_bonus',    // Tier upgrade bonus
                'sustainability_bonus',  // Carbon reduction bonus
                'loyalty_adjustment'     // Admin adjustment
            ]);
            
            // Amount
            $table->bigInteger('points_amount');
            $table->text('description')->nullable();
            
            // Related entity
            $table->string('related_entity_type')->nullable(); // 'order', 'referral', etc
            $table->unsignedBigInteger('related_entity_id')->nullable();
            
            // Balance tracking
            $table->bigInteger('balance_before')->default(0);
            $table->bigInteger('balance_after')->default(0);
            
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index('vendor_id');
            $table->index('transaction_type');
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('loyalty_points_ledger');
    }
};

// database/migrations/2024_01_01_000031_create_loyalty_rewards_catalog_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('loyalty_rewards_catalog', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Reward details
            $table->string('reward_name');
            $table->text('description');
            
            // Cost & Availability
            $table->bigInteger('points_required');
            $table->integer('quantity_available')->nullable(); // null = unlimited
            $table->integer('quantity_redeemed')->default(0);
            
            // Reward type
            $table->enum('reward_type', [
                'discount_voucher',      // Discount on next purchase
                'free_shipping',         // Free shipping coupon
                'product_credit',        // Credit for products
                'feature_upgrade',       // Premium feature access
                'exclusive_product',     // Exclusive product access
                'commission_boost',      // Higher commission rate
                'featured_listing'       // Featured placement
            ]);
            
            // Redemption details
            $table->string('redemption_code', 50)->unique()->nullable();
            $table->decimal('redemption_value', 12, 2)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Indexes
            $table->index('reward_type');
            $table->index('is_active');
            $table->index('points_required');
        });
    }

    public function down(): void {
        Schema::dropIfExists('loyalty_rewards_catalog');
    }
};

// database/migrations/2024_01_01_000032_create_loyalty_redemptions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('loyalty_redemptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained('loyalty_rewards_catalog')->cascadeOnDelete();
            
            // Redemption details
            $table->bigInteger('points_redeemed');
            $table->string('redemption_code', 50)->unique();
            
            // Status
            $table->enum('status', ['claimed', 'used', 'expired', 'cancelled'])->default('claimed');
            
            // Usage tracking
            $table->timestamp('used_at')->nullable();
            $table->string('used_for', 255)->nullable(); // What order/product it was used for
            
            // Expiry
            $table->date('expires_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('vendor_id');
            $table->index('reward_id');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('loyalty_redemptions');
    }
};

// ============================================================================
// 9. VENDOR QR CODE MANAGEMENT
// ============================================================================

// database/migrations/2024_01_01_000033_create_vendor_qr_codes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vendor_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            
            // QR Code details
            $table->string('qr_code_id', 255)->unique(); // Unique identifier
            $table->string('qr_code_image_url', 500);
            $table->string('qr_code_data', 255); // Encoded data
            
            // Status
            $table->enum('status', ['active', 'inactive', 'suspended', 'replaced'])
                  ->default('active');
            
            // Tracking
            $table->integer('scans_count')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            
            // Generation & Expiry
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            
            // Replacement (if new QR generated)
            $table->foreignId('replaced_by')->nullable()->constrained('vendor_qr_codes')->nullifyOnDelete();
            $table->timestamp('replaced_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('vendor_id');
            $table->index('qr_code_id');
            $table->index('status');
        });
    }

    public function down(): void {
        Schema::dropIfExists('vendor_qr_codes');
    }
};

// database/migrations/2024_01_01_000034_create_qr_scan_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('qr_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_qr_code_id')->constrained()->cascadeOnDelete();
            
            // Scan context
            $table->string('scan_context', 100); // 'delivery_verification', 'order_tracking', etc
            
            // Scanned by
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullifyOnDelete();
            $table->enum('scanner_type', ['delivery_partner', 'vendor', 'admin', 'system'])->nullable();
            
            // Location
            $table->string('scan_location', 255)->nullable();
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Related entity
            $table->string('related_entity_type')->nullable(); // 'delivery', 'order', etc
            $table->unsignedBigInteger('related_entity_id')->nullable();
            
            // Device info
            $table->string('device_info', 255)->nullable(); // Mobile device info
            $table->ipAddress('ip_address')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index('vendor_qr_code_id');
            $table->index('scanned_by');
            $table->index('scan_context');
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('qr_scan_logs');
    }
};

// ============================================================================
// 10. ADMIN DASHBOARD TRACKING
// ============================================================================

// database/migrations/2024_01_01_000035_create_admin_review_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('admin_review_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Admin who performed action
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            
            // What was reviewed
            $table->string('entity_type'); // 'vendor', 'order', 'kyc_documents'
            $table->unsignedBigInteger('entity_id');
            
            // Action taken
            $table->enum('action', [
                'approved',
                'rejected',
                'requested_more_info',
                'assigned_for_verification',
                'verified_address',
                'activated',
                'suspended'
            ]);
            
            // Details
            $table->text('review_comments')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            
            // IP tracking
            $table->ipAddress('ip_address')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('admin_id');
            $table->index(['entity_type', 'entity_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('admin_review_logs');
    }
};

// ============================================================================
// END OF UPDATED MIGRATIONS
// ============================================================================
