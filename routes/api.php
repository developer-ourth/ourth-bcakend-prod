<?php

use App\Http\Controllers\Api\Admin\UploadController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\Consumer\AddressController;
use App\Http\Controllers\Api\Consumer\CartController;
use App\Http\Controllers\Api\Consumer\DeviceTokenController;
use App\Http\Controllers\Api\Consumer\MobileOrderController;
use App\Http\Controllers\Api\Consumer\NotificationController;
use App\Http\Controllers\Api\Consumer\PaymentMethodController;
use App\Http\Controllers\Api\Consumer\ProfileController;
use App\Http\Controllers\Api\Consumer\QrScanController;
use App\Http\Controllers\Api\Consumer\RatingController;
use App\Http\Controllers\Api\Consumer\RewardController;
use App\Http\Controllers\Api\Consumer\SubscriptionController;
use App\Http\Controllers\Api\Consumer\TaxProfileController;
use App\Http\Controllers\Api\Consumer\VendorDiscoveryController;
use App\Http\Controllers\Api\Consumer\WishlistController;
use App\Http\Controllers\Api\Dashboard\AdminDashboardController;
use App\Http\Controllers\Api\Dashboard\ConsumerDashboardController;
use App\Http\Controllers\Api\Dashboard\FinanceDashboardController;
use App\Http\Controllers\Api\Dashboard\FounderDashboardController;
use App\Http\Controllers\Api\Dashboard\MarketingDashboardController;
use App\Http\Controllers\Api\Dashboard\OperationsDashboardController;
use App\Http\Controllers\Api\Dashboard\VendorDashboardController;
use App\Http\Controllers\Api\Dashboard\WasteManagementDashboardController;
use App\Http\Controllers\Api\KYCApprovalController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

/**
 * API v1 Routes
 *
 * All routes are versioned to allow multiple API versions in future.
 * Phase 1: v1 endpoints for vendor onboarding
 * Phase 2: Can add /api/v2 routes for new services/features without breaking v1
 */
Route::prefix('v1')->group(function () {

    Route::get('/media/{path}', [MediaController::class, 'show'])
        ->where('path', '.*')
        ->name('media.show');

    // Authentication Routes (no auth required)
    // Throttle: 10 attempts per minute per IP to prevent brute-force attacks
    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/login-vendor', [AuthController::class, 'loginWithVendorId']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
        Route::get('/vendor-status', [AuthController::class, 'vendorStatus'])->middleware('auth:sanctum');
    });

    // Phase 1: Vendor Onboarding & KYC
    Route::prefix('vendors')->group(function () {
        // Public endpoints (no auth required for registration)
        Route::post('/register', [VendorController::class, 'register']);
        Route::post('/kyc/upload', [VendorController::class, 'uploadKyc']);
        Route::get('/{vendor}/approval-status', [VendorController::class, 'approvalStatus']);
        Route::get('/{vendor}/qr', [VendorController::class, 'getQrCode']);

        // Protected endpoints (auth required)
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/', [VendorController::class, 'index']); // List all vendors
            Route::get('/{vendor}', [VendorController::class, 'show']); // Get vendor details
            Route::post('/resend-vendor-id-email', [VendorController::class, 'resendVendorIdEmail']); // Resend vendor ID email
        });

        // Admin endpoints
        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::post('/{vendor}/approve', [VendorController::class, 'approve']);
            Route::post('/{vendor}/reject', [VendorController::class, 'reject']);
        });
    });

    // KYC Approval Management (Admin only)
    Route::prefix('kyc-approvals')->middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/', [KYCApprovalController::class, 'index']); // List pending KYC approvals
        Route::get('/{vendor}', [KYCApprovalController::class, 'show']); // Get KYC details
        Route::post('/{vendor}/approve', [KYCApprovalController::class, 'approve']); // Approve KYC
        Route::post('/{vendor}/reject', [KYCApprovalController::class, 'reject']); // Reject KYC
    });

    // Order Management (Admin + Operations)
    Route::prefix('orders')->middleware(['auth:sanctum', 'role:admin,operations,founder'])->group(function () {
        Route::get('/', [OrderController::class, 'index']); // List all orders
        Route::get('/stats', [OrderController::class, 'stats']); // Get order statistics
        Route::post('/', [OrderController::class, 'store']); // Create order
        Route::get('/{order}', [OrderController::class, 'show']); // Get order details
        Route::post('/{order}/confirm', [OrderController::class, 'confirm']); // Confirm order (pending → confirmed)
        Route::post('/{order}/process', [OrderController::class, 'process']); // Ready Box (confirmed → processing)
        Route::post('/{order}/dispatch', [OrderController::class, 'dispatch']); // Dispatch (processing → out_for_delivery)
        Route::post('/{order}/deliver', [OrderController::class, 'deliver']); // Mark order as delivered
        Route::post('/{order}/cancel', [OrderController::class, 'cancel']); // Cancel order
        Route::post('/{order}/tracking/location', [MobileOrderController::class, 'updateLocation']); // Update rider GPS
    });

    // User Management (Admin only)
    Route::prefix('users')->middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/', [UserController::class, 'index']); // List all users
        Route::get('/stats', [UserController::class, 'stats']); // Get user statistics
        Route::get('/{user}', [UserController::class, 'show']); // Get user details
        Route::patch('/{user}/role', [UserController::class, 'updateRole']); // Update user role
    });

    // ── Marketplace: Products & Categories ────────────────────────────────────

    // Public product & category listing (no auth required)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    // Admin product & category management
    Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/upload-image', [UploadController::class, 'image']);

        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);

        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    });

    // =========================================================================
    // Dashboard Routes (Phase 2)
    // =========================================================================
    Route::prefix('dashboard')->middleware('auth:sanctum')->group(function () {

        // 1. Founder / CXO Dashboard
        Route::middleware('role:founder,admin')->group(function () {
            Route::get('/founder', [FounderDashboardController::class, 'index']);
            Route::get('/founder/kpis', [FounderDashboardController::class, 'kpis']);
            Route::get('/founder/products', [FounderDashboardController::class, 'products']);
        });

        // 2. Vendor / Hawker Dashboard
        Route::middleware('role:vendor,admin')->prefix('vendor')->group(function () {
            Route::get('/{vendor}', [VendorDashboardController::class, 'index']);
            Route::get('/{vendor}/earnings', [VendorDashboardController::class, 'earnings']);
            Route::get('/{vendor}/catalog', [VendorDashboardController::class, 'catalog']);
        });

        // 3. Consumer Dashboard
        Route::middleware('role:consumer,admin')->prefix('consumer')->group(function () {
            Route::get('/{user}', [ConsumerDashboardController::class, 'index']);
            Route::get('/{user}/nearby-vendors', [ConsumerDashboardController::class, 'nearbyVendors']);
            Route::get('/{user}/rewards', [ConsumerDashboardController::class, 'rewardsSummary']);
        });

        // 4. Operations & Logistics Dashboard
        Route::middleware('role:operations,admin')->group(function () {
            Route::get('/operations', [OperationsDashboardController::class, 'index']);
            Route::get('/operations/routes', [OperationsDashboardController::class, 'routes']);
            Route::get('/operations/inventory', [OperationsDashboardController::class, 'inventory']);
        });

        // 5. Waste Management Dashboard
        Route::middleware('role:waste_management,admin')->group(function () {
            Route::get('/waste-management', [WasteManagementDashboardController::class, 'index']);
            Route::get('/waste-management/dustbins', [WasteManagementDashboardController::class, 'dustbins']);
            Route::get('/waste-management/collections', [WasteManagementDashboardController::class, 'collections']);
        });

        // 6. Finance & Investor Dashboard
        Route::middleware('role:finance,founder,admin')->group(function () {
            Route::get('/finance', [FinanceDashboardController::class, 'index']);
            Route::get('/finance/snapshots', [FinanceDashboardController::class, 'snapshots']);
        });

        // 7. Admin / Control Panel Dashboard
        Route::middleware('role:admin')->group(function () {
            Route::get('/admin', [AdminDashboardController::class, 'index']);
            Route::get('/admin/users', [AdminDashboardController::class, 'users']);
            Route::get('/admin/cities', [AdminDashboardController::class, 'cities']);
            Route::get('/admin/campaigns', [AdminDashboardController::class, 'campaigns']);
            Route::get('/admin/alerts', [AdminDashboardController::class, 'alerts']);
        });

        // 8. Marketing & Growth Dashboard
        Route::middleware('role:marketing,admin')->group(function () {
            Route::get('/marketing', [MarketingDashboardController::class, 'index']);
            Route::get('/marketing/campaigns/{campaign}', [MarketingDashboardController::class, 'campaignDetail']);
        });
    });

    // =========================================================================
    // Mobile App — Consumer API (Phase 1)
    // =========================================================================

    // Public discovery: no auth required
    Route::prefix('vendors')->group(function () {
        Route::get('/', [VendorDiscoveryController::class, 'index']);
        Route::get('/{vendor}', [VendorDiscoveryController::class, 'show']);
    });
    Route::get('/products', [VendorDiscoveryController::class, 'products']);

    // Authenticated consumer endpoints
    Route::prefix('me')->middleware('auth:sanctum')->group(function () {

        // Profile
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);

        // Addresses
        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::patch('/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);

        // Cart — vendor-only (B2D MVP; role broadened to 'consumer' for future B2C)
        Route::middleware('role:vendor,admin')->group(function () {
            Route::get('/cart', [CartController::class, 'show']);
            Route::post('/cart/items', [CartController::class, 'addItem']);
            Route::patch('/cart/items/{item}', [CartController::class, 'updateItem']);
            Route::delete('/cart/items/{item}', [CartController::class, 'removeItem']);
            Route::delete('/cart', [CartController::class, 'clear']);
        });

        // Orders — vendor-only (B2D MVP)
        Route::middleware('role:vendor,admin')->group(function () {
            Route::get('/orders', [MobileOrderController::class, 'index']);
            Route::post('/orders', [MobileOrderController::class, 'store']);
            Route::get('/orders/{order}', [MobileOrderController::class, 'show']);
            Route::post('/orders/{order}/cancel', [MobileOrderController::class, 'cancel']);
            Route::get('/orders/{order}/invoice', [MobileOrderController::class, 'invoice']);
            Route::get('/orders/{order}/tracking', [MobileOrderController::class, 'tracking']);
            Route::post('/orders/{order}/payments/razorpay/initiate', [MobileOrderController::class, 'initiateRazorpayPayment']);
            Route::post('/orders/{order}/payments/razorpay/verify', [MobileOrderController::class, 'verifyRazorpayPayment']);
        });

        // Device push tokens
        Route::post('/device-token', [DeviceTokenController::class, 'store']);
        Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);

        // Subscriptions
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/subscriptions', [SubscriptionController::class, 'store']);
        Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show']);
        Route::patch('/subscriptions/{subscription}', [SubscriptionController::class, 'update']);

        // Rewards
        Route::get('/rewards', [RewardController::class, 'index']);
        Route::get('/rewards/catalog', [RewardController::class, 'catalog']);
        Route::post('/rewards/redeem', [RewardController::class, 'redeem']);

        // QR Scan
        Route::post('/qr/scan', [QrScanController::class, 'scan']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);

        // Ratings
        Route::post('/ratings', [RatingController::class, 'store']);

        // Payment Methods
        Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
        Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
        Route::patch('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update']);
        Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy']);

        // Tax / GST Profile
        Route::get('/tax-profile', [TaxProfileController::class, 'show']);
        Route::put('/tax-profile', [TaxProfileController::class, 'upsert']);

        // Wishlist / Collections
        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist', [WishlistController::class, 'store']);
        Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy']);
    });
});
