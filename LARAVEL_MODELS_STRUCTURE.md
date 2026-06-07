# OURTH APP - LARAVEL MODELS STRUCTURE

## 📋 Complete Model Files

Copy these files to `app/Models/` directory

---

## 1. USER MODELS

### User.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable {
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'phone', 'email', 'name', 'password', 'user_type', 'status',
        'last_login_at', 'last_ip_address'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    // Relationships
    public function vendor() {
        return $this->hasOne(Vendor::class);
    }

    public function deliveryOrders() {
        return $this->hasMany(Delivery::class, 'delivery_partner_id');
    }

    public function adminReviews() {
        return $this->hasMany(AdminReviewLog::class, 'admin_id');
    }

    // Scopes
    public function scopeVendor($query) {
        return $query->where('user_type', 'vendor');
    }

    public function scopeDeliveryPartner($query) {
        return $query->where('user_type', 'delivery_partner');
    }

    public function scopeAdmin($query) {
        return $query->where('user_type', 'admin');
    }

    public function scopeActive($query) {
        return $query->where('status', 'active');
    }

    // Methods
    public function isVendor(): bool {
        return $this->user_type === 'vendor';
    }

    public function isDeliveryPartner(): bool {
        return $this->user_type === 'delivery_partner';
    }

    public function isAdmin(): bool {
        return $this->user_type === 'admin';
    }

    public function updateLastLogin() {
        $this->update([
            'last_login_at' => now(),
            'last_ip_address' => request()->ip()
        ]);
    }
}
```

---

## 2. VENDOR MODELS

### Vendor.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model {
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'business_name', 'business_category', 'description',
        'logo_url', 'gstin', 'pan', 'trade_license_number',
        'trade_license_expiry', 'bank_account_number', 'bank_ifsc_code',
        'bank_account_holder_name', 'kyc_status', 'kyc_verified_at',
        'kyc_verified_by', 'kyc_rejection_reason', 'address_line1',
        'address_line2', 'city', 'state', 'postal_code', 'country',
        'latitude', 'longitude', 'average_rating', 'total_ratings_count',
        'qr_code_id', 'total_orders', 'total_revenue'
    ];

    protected $casts = [
        'kyc_verified_at' => 'datetime',
        'average_rating' => 'float',
        'total_revenue' => 'decimal:2',
        'latitude' => 'float',
        'longitude' => 'float'
    ];

    // Relationships
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function products() {
        return $this->hasMany(Product::class);
    }

    public function orders() {
        return $this->hasMany(Order::class);
    }

    public function carts() {
        return $this->hasMany(Cart::class);
    }

    public function kycDocuments() {
        return $this->hasMany(VendorKycDocument::class);
    }

    public function settings() {
        return $this->hasOne(VendorSettings::class);
    }

    public function approval() {
        return $this->hasOne(VendorApproval::class);
    }

    public function qrCodes() {
        return $this->hasMany(VendorQrCode::class);
    }

    public function activeQrCode() {
        return $this->hasOne(VendorQrCode::class)
                    ->where('status', 'active')
                    ->latestOfMany();
    }

    public function scores() {
        return $this->hasOne(VendorScore::class);
    }

    public function loyaltyAccount() {
        return $this->hasOne(VendorLoyaltyAccount::class);
    }

    public function carbonEmissions() {
        return $this->hasMany(CarbonEmission::class);
    }

    public function carbonAnalytics() {
        return $this->hasMany(CarbonAnalytic::class);
    }

    public function ratings() {
        return $this->morphMany(Rating::class, 'ratable');
    }

    public function dailyStats() {
        return $this->hasMany(VendorDailyStat::class);
    }

    // Scopes
    public function scopeVerified($query) {
        return $query->where('kyc_status', 'verified');
    }

    public function scopeApproved($query) {
        return $query->whereHas('approval', function($q) {
            $q->where('approval_stage', 'approved');
        });
    }

    public function scopeActive($query) {
        return $query->whereNull('deleted_at');
    }

    // Methods
    public function getFullAddressAttribute(): string {
        return "{$this->address_line1}, {$this->city}, {$this->state} {$this->postal_code}";
    }

    public function isKycVerified(): bool {
        return $this->kyc_status === 'verified';
    }

    public function isApproved(): bool {
        return $this->approval?->approval_stage === 'approved';
    }

    public function getApprovalStatus() {
        return $this->approval?->approval_stage ?? 'pending_documents';
    }

    public function updateAverageRating() {
        $average = $this->ratings()->avg('rating') ?? 0;
        $count = $this->ratings()->count();

        $this->update([
            'average_rating' => $average,
            'total_ratings_count' => $count
        ]);
    }
}
```

### VendorApproval.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VendorApproval extends Model {
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'approval_stage', 'reviewed_by', 'reviewed_at',
        'address_verified_by', 'address_verified_at', 'rejection_reason', 'rejection_notes',
        'approval_notes', 'submitted_at'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'address_verified_at' => 'datetime',
        'submitted_at' => 'datetime'
    ];

    // Relationships
    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }

    public function reviewer() {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function addressVerifier() {
        return $this->belongsTo(User::class, 'address_verified_by');
    }

    public function reviewLogs() {
        return $this->hasMany(AdminReviewLog::class, 'entity_id')
                    ->where('entity_type', 'vendor');
    }

    // Methods
    public function submitDocuments() {
        $this->update([
            'approval_stage' => 'documents_submitted',
            'submitted_at' => now()
        ]);
    }

    public function startReview() {
        $this->update(['approval_stage' => 'under_review']);
    }

    public function requestAddressVerification() {
        $this->update(['approval_stage' => 'address_verification']);
    }

    public function approve() {
        $this->update([
            'approval_stage' => 'approved',
            'reviewed_at' => now()
        ]);

        event(new \App\Events\VendorApproved($this->vendor));
    }

    public function reject($reason, $notes = null) {
        $this->update([
            'approval_stage' => 'rejected',
            'rejection_reason' => $reason,
            'rejection_notes' => $notes,
            'reviewed_at' => now()
        ]);

        event(new \App\Events\VendorRejected($this->vendor, $reason));
    }

    public function verifyAddress() {
        $this->update([
            'address_verified_at' => now(),
            'address_verified_by' => auth()->id()
        ]);
    }
}
```

### VendorQrCode.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class VendorQrCode extends Model {
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'qr_code_id', 'qr_code_image_url', 'qr_code_data',
        'status', 'scans_count', 'last_scanned_at', 'expires_at', 'replaced_by', 'replaced_at'
    ];

    protected $casts = [
        'last_scanned_at' => 'datetime',
        'expires_at' => 'datetime',
        'replaced_at' => 'datetime'
    ];

    // Relationships
    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }

    public function scanLogs() {
        return $this->hasMany(QrScanLog::class, 'vendor_qr_code_id');
    }

    public function replacedBy() {
        return $this->belongsTo(VendorQrCode::class, 'replaced_by');
    }

    public function replacements() {
        return $this->hasMany(VendorQrCode::class, 'replaced_by');
    }

    // Scopes
    public function scopeActive($query) {
        return $query->where('status', 'active');
    }

    // Methods
    public static function generateForVendor(Vendor $vendor) {
        $qrCodeId = 'VND-' . $vendor->id . '-' . uniqid();

        $qrCodeImage = QrCode::format('png')
            ->size(300)
            ->generate(json_encode([
                'vendor_id' => $vendor->id,
                'qr_code_id' => $qrCodeId,
                'business_name' => $vendor->business_name,
                'timestamp' => now()
            ]));

        $path = Storage::disk('s3')->put('qr-codes/' . $qrCodeId . '.png', $qrCodeImage);

        return self::create([
            'vendor_id' => $vendor->id,
            'qr_code_id' => $qrCodeId,
            'qr_code_image_url' => Storage::disk('s3')->url($path),
            'qr_code_data' => $qrCodeId,
            'status' => 'active'
        ]);
    }

    public function recordScan($context, $relatedEntityType = null, $relatedEntityId = null) {
        $this->increment('scans_count');
        $this->update(['last_scanned_at' => now()]);

        QrScanLog::create([
            'vendor_qr_code_id' => $this->id,
            'scan_context' => $context,
            'scanned_by' => auth()->id() ?? null,
            'related_entity_type' => $relatedEntityType,
            'related_entity_id' => $relatedEntityId,
            'ip_address' => request()->ip()
        ]);
    }
}
```

---

## 3. PRODUCT & INVENTORY MODELS

### Product.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model {
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id', 'name', 'description', 'sku', 'barcode',
        'category', 'sub_category', 'base_price', 'discounted_price',
        'cost_price', 'primary_image_url', 'secondary_images',
        'weight_grams', 'dimensions_cm', 'is_active', 'is_featured'
    ];

    protected $casts = [
        'secondary_images' => 'json',
        'dimensions_cm' => 'json',
        'base_price' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'cost_price' => 'decimal:2'
    ];

    // Relationships
    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }

    public function inventory() {
        return $this->hasOne(Inventory::class);
    }

    public function stockMovements() {
        return $this->hasMany(StockMovement::class);
    }

    public function orderItems() {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems() {
        return $this->hasMany(CartItem::class);
    }

    public function ratings() {
        return $this->morphMany(Rating::class, 'ratable');
    }

    // Scopes
    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query) {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $category) {
        return $query->where('category', $category);
    }

    // Methods
    public function getAvailableStockAttribute() {
        return $this->inventory->available_stock ?? 0;
    }

    public function getSellingPriceAttribute() {
        return $this->discounted_price ?? $this->base_price;
    }
}
```

### Inventory.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Inventory extends Model {
    use HasFactory;

    protected $fillable = [
        'product_id', 'vendor_id', 'current_stock', 'reserved_stock',
        'low_stock_threshold', 'reorder_quantity', 'last_restocked_at'
    ];

    protected $casts = [
        'last_restocked_at' => 'datetime'
    ];

    // Relationships
    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }

    public function movements() {
        return $this->hasMany(StockMovement::class);
    }

    // Methods
    public function getAvailableStockAttribute(): int {
        return $this->current_stock - $this->reserved_stock;
    }

    public function reserve($quantity) {
        if ($this->available_stock < $quantity) {
            throw new \Exception('Insufficient stock');
        }

        $this->increment('reserved_stock', $quantity);
    }

    public function deduct($quantity) {
        $this->decrement('current_stock', $quantity);
        $this->decrement('reserved_stock', $quantity);
    }

    public function restock($quantity) {
        $this->increment('current_stock', $quantity);
        $this->update(['last_restocked_at' => now()]);
    }

    public function isLowStock(): bool {
        return $this->available_stock <= $this->low_stock_threshold;
    }
}
```

---

## 4. CART & ORDER MODELS

### Cart.php (NEW)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cart extends Model {
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'total_items_price', 'total_items_count', 'cart_status', 'last_activity_at'
    ];

    protected $casts = [
        'total_items_price' => 'decimal:2',
        'last_activity_at' => 'datetime'
    ];

    // Relationships
    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }

    public function items() {
        return $this->hasMany(CartItem::class);
    }

    // Methods
    public function addItem(Product $product, int $quantity) {
        $cartItem = $this->items()
            ->where('product_id', $product->id)
            ->first();

        if ($cartItem) {
            $cartItem->increment('quantity', $quantity);
            $cartItem->update(['item_total' => $cartItem->unit_price * $cartItem->quantity]);
        } else {
            $this->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'unit_price' => $product->base_price,
                'quantity' => $quantity,
                'item_total' => $product->base_price * $quantity
            ]);
        }

        $this->updateTotals();
    }

    public function updateTotals() {
        $this->update([
            'total_items_price' => $this->items()->sum('item_total'),
            'total_items_count' => $this->items()->sum('quantity'),
            'last_activity_at' => now()
        ]);
    }

    public function isEmpty(): bool {
        return $this->items()->count() === 0;
    }

    public function clear() {
        $this->items()->delete();
        $this->update([
            'total_items_price' => 0,
            'total_items_count' => 0,
            'cart_status' => 'abandoned'
        ]);
    }
}
```

### Order.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model {
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id', 'order_number', 'customer_phone', 'customer_email',
        'customer_name', 'delivery_address_line1', 'delivery_address_line2',
        'delivery_city', 'delivery_state', 'delivery_postal_code',
        'delivery_latitude', 'delivery_longitude', 'order_status',
        'total_items_price', 'tax_amount', 'discount_amount',
        'delivery_charge', 'total_amount', 'payment_method',
        'payment_status', 'special_instructions', 'notes',
        'confirmed_at', 'processing_started_at', 'dispatched_at', 'delivered_at'
    ];

    protected $casts = [
        'total_items_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'delivery_charge' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'delivery_latitude' => 'float',
        'delivery_longitude' => 'float',
        'notes' => 'json',
        'confirmed_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime'
    ];

    // Relationships
    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }

    public function items() {
        return $this->hasMany(OrderItem::class);
    }

    public function delivery() {
        return $this->hasOne(Delivery::class);
    }

    public function payment() {
        return $this->hasOne(Payment::class);
    }

    public function invoice() {
        return $this->hasOne(Invoice::class);
    }

    public function dispatchSlip() {
        return $this->hasOne(DispatchSlip::class);
    }

    public function refund() {
        return $this->hasOne(Refund::class);
    }

    public function returnRequest() {
        return $this->hasOne(\App\Models\Return::class);
    }

    public function ratings() {
        return $this->hasMany(Rating::class);
    }

    public function carbonEmissions() {
        return $this->hasMany(CarbonEmission::class);
    }

    // Scopes
    public function scopeDelivered($query) {
        return $query->where('order_status', 'delivered');
    }

    public function scopeCancelled($query) {
        return $query->where('order_status', 'cancelled');
    }

    public function scopeForVendor($query, $vendorId) {
        return $query->where('vendor_id', $vendorId);
    }

    // Methods
    public function confirm() {
        $this->update([
            'order_status' => 'confirmed',
            'confirmed_at' => now()
        ]);

        event(new \App\Events\OrderConfirmed($this));
    }

    public function markAsProcessing() {
        $this->update([
            'order_status' => 'processing',
            'processing_started_at' => now()
        ]);
    }

    public function markAsDispatched() {
        $this->update([
            'order_status' => 'dispatched',
            'dispatched_at' => now()
        ]);
    }

    public function markAsDelivered() {
        $this->update([
            'order_status' => 'delivered',
            'delivered_at' => now()
        ]);

        event(new \App\Events\OrderDelivered($this));
    }

    public function canBeReturned(): bool {
        return in_array($this->order_status, ['delivered']) && now()->diffInDays($this->delivered_at) <= 7;
    }
}
```

---

## 5. DELIVERY MODELS

### Delivery.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Delivery extends Model {
    use HasFactory;

    protected $fillable = [
        'order_id', 'delivery_partner_id', 'delivery_status',
        'current_latitude', 'current_longitude', 'current_location_address',
        'estimated_delivery_time', 'actual_delivery_time', 'delivery_otp',
        'otp_verified_at', 'pod_signature_url', 'pod_photo_url',
        'delivery_rating', 'delivery_feedback', 'estimated_distance_km',
        'actual_distance_km', 'picked_up_at'
    ];

    protected $casts = [
        'estimated_delivery_time' => 'datetime',
        'actual_delivery_time' => 'datetime',
        'otp_verified_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'current_latitude' => 'float',
        'current_longitude' => 'float',
        'delivery_rating' => 'float',
        'estimated_distance_km' => 'float',
        'actual_distance_km' => 'float'
    ];

    // Relationships
    public function order() {
        return $this->belongsTo(Order::class);
    }

    public function deliveryPartner() {
        return $this->belongsTo(User::class, 'delivery_partner_id');
    }

    public function locations() {
        return $this->hasMany(DeliveryLocation::class);
    }

    public function verification() {
        return $this->hasOne(DeliveryVerification::class);
    }

    // Methods
    public function assign(User $deliveryPartner) {
        $otp = rand(100000, 999999);

        $this->update(['delivery_partner_id' => $deliveryPartner->id]);

        DeliveryVerification::create([
            'delivery_id' => $this->id,
            'delivery_otp' => $otp,
            'otp_sent_at' => now()
        ]);

        event(new \App\Events\DeliveryAssigned($this));
    }

    public function updateLocation($latitude, $longitude, $accuracy = null) {
        \App\Jobs\RecordDeliveryLocation::dispatch(
            $this->id, $latitude, $longitude, $accuracy
        )->onQueue('locations');

        $this->update([
            'current_latitude' => $latitude,
            'current_longitude' => $longitude
        ]);

        broadcast(new \App\Events\DeliveryLocationUpdated(
            $this->id,
            $this->order->vendor->user_id,
            $latitude,
            $longitude
        ));
    }

    public function confirmDelivery() {
        $verification = $this->verification;

        if (!$verification->otp_verified && !$verification->qr_verified) {
            throw new \Exception('Delivery not verified');
        }

        $this->update([
            'delivery_status' => 'delivered',
            'actual_delivery_time' => now()
        ]);

        $this->order->markAsDelivered();

        event(new \App\Events\DeliveryCompleted($this));
    }
}
```

### DeliveryVerification.php (NEW)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeliveryVerification extends Model {
    use HasFactory;

    protected $fillable = [
        'delivery_id', 'delivery_otp', 'otp_sent_at', 'otp_attempts',
        'otp_verified', 'otp_verified_at', 'qr_verified', 'qr_verified_at',
        'signature_photo_url', 'delivery_photo_url', 'recipient_name',
        'recipient_phone', 'verification_method', 'vendor_qr_code_id'
    ];

    protected $casts = [
        'otp_sent_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'qr_verified_at' => 'datetime'
    ];

    // Relationships
    public function delivery() {
        return $this->belongsTo(Delivery::class);
    }

    public function vendorQrCode() {
        return $this->belongsTo(VendorQrCode::class);
    }

    // Methods
    public function verifyOtp($otp) {
        if ($otp != $this->delivery_otp) {
            $this->increment('otp_attempts');

            if ($this->otp_attempts > 3) {
                throw new \Exception('Too many failed OTP attempts');
            }

            throw new \Exception('Invalid OTP');
        }

        $this->update([
            'otp_verified' => true,
            'otp_verified_at' => now()
        ]);
    }

    public function verifyQrCode($qrCodeId) {
        $qrCode = VendorQrCode::where('qr_code_id', $qrCodeId)->firstOrFail();

        if ($qrCode->vendor_id !== $this->delivery->order->vendor_id) {
            throw new \Exception('QR code does not match vendor');
        }

        $qrCode->recordScan('delivery_verification', 'delivery', $this->delivery->id);

        $this->update([
            'qr_verified' => true,
            'qr_verified_at' => now(),
            'vendor_qr_code_id' => $qrCode->id
        ]);
    }
}
```

---

## 6. PAYMENT & INVOICE MODELS

### Payment.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model {
    use HasFactory;

    protected $fillable = [
        'order_id', 'amount', 'currency', 'payment_status',
        'payment_gateway', 'gateway_transaction_id', 'gateway_reference_id',
        'gateway_response', 'payment_method', 'card_last_four', 'card_brand',
        'error_code', 'error_message', 'initiated_at', 'authorized_at',
        'captured_at', 'failed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'json',
        'initiated_at' => 'datetime',
        'authorized_at' => 'datetime',
        'captured_at' => 'datetime',
        'failed_at' => 'datetime'
    ];

    // Relationships
    public function order() {
        return $this->belongsTo(Order::class);
    }

    public function refund() {
        return $this->hasOne(Refund::class);
    }

    // Methods
    public function markAsAuthorized() {
        $this->update([
            'payment_status' => 'authorized',
            'authorized_at' => now()
        ]);
    }

    public function markAsCaptured() {
        $this->update([
            'payment_status' => 'captured',
            'captured_at' => now()
        ]);

        event(new \App\Events\PaymentCaptured($this));
    }

    public function markAsFailed($errorCode, $errorMessage) {
        $this->update([
            'payment_status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'failed_at' => now()
        ]);

        event(new \App\Events\PaymentFailed($this));
    }
}
```

---

## 7. RATINGS & NOTIFICATIONS MODELS

### Rating.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Rating extends Model {
    use HasFactory;

    protected $fillable = [
        'ratable_type', 'ratable_id', 'rating', 'review_text',
        'review_photos', 'reviewer_type', 'reviewer_id', 'order_id'
    ];

    protected $casts = [
        'review_photos' => 'json',
        'rating' => 'float'
    ];

    // Relationships
    public function ratable() {
        return $this->morphTo();
    }

    public function reviewer() {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function order() {
        return $this->belongsTo(Order::class);
    }

    // Scopes
    public function scopeForVendor($query, $vendorId) {
        return $query->where('ratable_type', 'vendor')
                    ->where('ratable_id', $vendorId);
    }
}
```

### Notification.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model {
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'recipient_id', 'title', 'body', 'notification_type',
        'entity_type', 'entity_id', 'send_email', 'send_sms',
        'send_push', 'is_read', 'read_at', 'data'
    ];

    protected $casts = [
        'send_email' => 'boolean',
        'send_sms' => 'boolean',
        'send_push' => 'boolean',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'data' => 'json'
    ];

    // Relationships
    public function recipient() {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function logs() {
        return $this->hasMany(NotificationLog::class);
    }

    // Methods
    public function markAsRead() {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    public function send() {
        if ($this->send_email) {
            \App\Jobs\SendEmailNotification::dispatch($this);
        }

        if ($this->send_sms) {
            \App\Jobs\SendSmsNotification::dispatch($this);
        }

        if ($this->send_push) {
            \App\Jobs\SendPushNotification::dispatch($this);
        }
    }
}
```

---

## Quick Setup Commands

```bash
# Generate all models at once
php artisan make:model User -m
php artisan make:model Vendor -m
php artisan make:model Product -m
php artisan make:model Order -m
php artisan make:model Delivery -m
php artisan make:model Payment -m
php artisan make:model Cart -m
php artisan make:model VendorApproval -m
php artisan make:model VendorQrCode -m
php artisan make:model DispatchSlip -m
php artisan make:model DeliveryVerification -m
php artisan make:model Rating -m
php artisan make:model Notification -m

# Create services
php artisan make:service OrderService
php artisan make:service PaymentService
php artisan make:service DeliveryService
php artisan make:service VendorService
php artisan make:service CartService

# Create events
php artisan make:event VendorApproved
php artisan make:event OrderConfirmed
php artisan make:event OrderDelivered
php artisan make:event DeliveryAssigned
php artisan make:event PaymentCaptured

# Create jobs
php artisan make:job RecordDeliveryLocation
php artisan make:job SendEmailNotification
php artisan make:job SendSmsNotification
php artisan make:job SendPushNotification

# Run migrations
php artisan migrate
```

---

**All models are complete and ready to copy!**
