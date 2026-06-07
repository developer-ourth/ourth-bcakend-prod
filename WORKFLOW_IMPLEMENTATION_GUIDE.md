# OURTH APP - WORKFLOW IMPLEMENTATION GUIDE
## Database Schema Aligned with Workflow Document

---

## 📋 Document Overview

This guide maps the **OURTH Workflow Document** to the **Updated Database Schema** and provides implementation details for each workflow stage.

---

## 🔄 Complete Workflow Mapping

### STAGE 1: VENDOR ONBOARDING & KYC VALIDATION

#### Workflow Requirement:
```
Vendor Registration → KYC Document Upload → Admin Review → Approval → QR Code Generation
```

#### Database Implementation:

```
┌─────────────────────────────────────────────────────────────┐
│                    STAGE 1 FLOW                              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. Vendor Registration (users + vendors table)             │
│     └─ Creates USER with user_type = 'vendor'             │
│     └─ Creates VENDOR record linked to user                │
│                                                              │
│  2. KYC Documents Upload (vendor_kyc_documents)            │
│     └─ GST Certificate                                     │
│     └─ Trade License                                       │
│     └─ PAN Card                                            │
│     └─ AADHAR                                              │
│     └─ Bank Statement                                      │
│                                                              │
│  3. Admin Review Workflow (vendor_approvals)               │
│     Status: pending_documents → documents_submitted        │
│             → under_review → address_verification          │
│             → approved / rejected                           │
│                                                              │
│  4. QR Code Generation (vendor_qr_codes)                  │
│     └─ Auto-generated after approval                      │
│     └─ Unique QR code per vendor                          │
│     └─ QR contains vendor identification data             │
│     └─ Used for authentication & tracking                 │
│     └─ Tracked via qr_scan_logs                           │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

#### Key Tables for Stage 1:
```
1. users
   - Stores vendor user account
   - Fields: phone, email, password, user_type='vendor'

2. vendors
   - Main vendor profile
   - Fields: business_name, gstin, trade_license, kyc_status
   - Updated to: kyc_status = 'verified'
   
3. vendor_kyc_documents
   - Document uploads
   - Types: gst_certificate, trade_license, pan_card, aadhar
   - Status: document_url, verified_at, verified_by

4. vendor_approvals (NEW)
   - Approval workflow tracking
   - Stages: pending_documents → documents_submitted → under_review
             → address_verification → approved/rejected
   - Tracks: reviewed_by (admin), reviewed_at, address_verified_by
   
5. vendor_qr_codes (NEW)
   - Auto-generated QR codes
   - Fields: qr_code_id, qr_code_image_url, status
   - Tracking: scans_count, last_scanned_at

6. qr_scan_logs (NEW)
   - Audit trail of QR scans
   - Tracks who scanned, where, when, why
```

#### Laravel Implementation:

```php
// app/Models/User.php
class User extends Authenticatable {
    protected $fillable = ['phone', 'email', 'name', 'password', 'user_type', 'status'];
    
    public function vendor() {
        return $this->hasOne(Vendor::class);
    }
    
    public function isVendor() {
        return $this->user_type === 'vendor';
    }
}

// app/Models/Vendor.php
class Vendor extends Model {
    protected $fillable = [
        'user_id', 'business_name', 'business_category', 'gstin',
        'trade_license_number', 'kyc_status', 'address_line1', 'city', 'state'
    ];
    
    public function user() {
        return $this->belongsTo(User::class);
    }
    
    public function kycDocuments() {
        return $this->hasMany(VendorKycDocument::class);
    }
    
    public function approvalWorkflow() {
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
}

// app/Models/VendorApproval.php (NEW)
class VendorApproval extends Model {
    protected $fillable = [
        'vendor_id', 'approval_stage', 'reviewed_by', 'reviewed_at',
        'address_verified_by', 'address_verified_at', 'rejection_reason'
    ];
    
    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }
    
    public function approvalTrail() {
        return $this->hasMany(AdminReviewLog::class, 'entity_id')
                    ->where('entity_type', 'vendor_approval');
    }
    
    // Status progression
    public function submitDocuments() {
        $this->update(['approval_stage' => 'documents_submitted']);
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
        // Trigger QR generation
        event(new VendorApproved($this->vendor));
    }
}

// app/Models/VendorQrCode.php (NEW)
class VendorQrCode extends Model {
    protected $fillable = [
        'vendor_id', 'qr_code_id', 'qr_code_image_url', 'qr_code_data',
        'status', 'scans_count', 'last_scanned_at'
    ];
    
    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }
    
    public function scanLogs() {
        return $this->hasMany(QrScanLog::class, 'vendor_qr_code_id');
    }
    
    // Generate QR code
    public static function generateForVendor(Vendor $vendor) {
        // 1. Generate unique QR code ID
        $qrCodeId = 'VND-' . $vendor->id . '-' . uniqid();
        
        // 2. Create QR code image
        $qrCode = QrCode::format('png')
            ->size(300)
            ->generate(json_encode([
                'vendor_id' => $vendor->id,
                'qr_code_id' => $qrCodeId,
                'business_name' => $vendor->business_name
            ]));
        
        // 3. Upload to S3
        $path = Storage::disk('s3')->put('qr-codes/' . $qrCodeId . '.png', $qrCode);
        
        // 4. Create record
        return self::create([
            'vendor_id' => $vendor->id,
            'qr_code_id' => $qrCodeId,
            'qr_code_image_url' => Storage::disk('s3')->url($path),
            'qr_code_data' => $qrCodeId,
            'status' => 'active'
        ]);
    }
}

// app/Events/VendorApproved.php (NEW)
class VendorApproved {
    public function __construct(public Vendor $vendor) {}
}

// app/Listeners/GenerateVendorQrCode.php (NEW)
class GenerateVendorQrCode {
    public function handle(VendorApproved $event) {
        VendorQrCode::generateForVendor($event->vendor);
    }
}
```

#### API Endpoints for Stage 1:

```php
// routes/api.php

// Registration
POST /api/v1/vendors/register
POST /api/v1/vendors/{id}/kyc/upload
POST /api/v1/vendors/{id}/kyc/submit

// Admin Review (Protected - admin only)
GET /api/v1/admin/vendors/pending-approval
PUT /api/v1/admin/vendors/{id}/approve
PUT /api/v1/admin/vendors/{id}/reject
PUT /api/v1/admin/vendors/{id}/verify-address

// QR Code
GET /api/v1/vendors/{id}/qr-code
GET /api/v1/vendors/qr/{qrCodeId}/details
```

---

### STAGE 2: VENDOR ORDERING (CART & CHECKOUT)

#### Workflow Requirement:
```
Vendor Login → Browse Products → Add to Cart → Checkout → Payment → Order Created
```

#### Database Implementation:

```
┌─────────────────────────────────────────────────────────────┐
│                    STAGE 2 FLOW                              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. Product Browsing (products + inventory)                │
│     └─ Vendor sees available products                      │
│     └─ Real-time inventory check                           │
│                                                              │
│  2. Add to Cart (carts + cart_items)                       │
│     └─ Create/Update cart for vendor                       │
│     └─ Add items with quantity                             │
│     └─ Calculate totals                                    │
│                                                              │
│  3. Checkout & Payment (payments)                          │
│     └─ Payment via gateway                                 │
│     └─ Payment status tracking                             │
│                                                              │
│  4. Order Creation (orders + order_items)                 │
│     └─ Convert cart to order                               │
│     └─ Reserve inventory (stock_movements)                 │
│     └─ Create order items from cart items                  │
│                                                              │
│  5. Order Confirmation (notifications)                    │
│     └─ Send vendor notification                            │
│     └─ SMS/Push confirmation                               │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

#### Key Tables for Stage 2:
```
1. products & inventory
   - Product catalog with stock levels
   
2. carts (NEW)
   - Vendor shopping carts
   - Status: active, abandoned, converted_to_order
   
3. cart_items (NEW)
   - Items in cart
   - Links to products
   
4. orders
   - Main order record
   - Status: pending → confirmed → processing
   
5. order_items
   - Line items in order
   
6. payments
   - Payment transaction
   
7. stock_movements
   - Inventory reserved when order confirmed
   
8. notifications
   - Order confirmation notifications
```

#### Laravel Implementation:

```php
// app/Models/Cart.php (NEW)
class Cart extends Model {
    protected $fillable = [
        'vendor_id', 'total_items_price', 'total_items_count', 'cart_status'
    ];
    
    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }
    
    public function items() {
        return $this->hasMany(CartItem::class);
    }
    
    public function addItem(Product $product, int $quantity) {
        $cartItem = $this->items()
            ->where('product_id', $product->id)
            ->first();
        
        if ($cartItem) {
            $cartItem->increment('quantity', $quantity);
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
    
    public function convertToOrder() {
        DB::transaction(function () {
            // Create order
            $order = Order::create([
                'vendor_id' => $this->vendor_id,
                'order_number' => 'ORD-' . date('Y') . '-' . str_pad(Order::max('id') + 1, 6, '0', STR_PAD_LEFT),
                'total_items_price' => $this->total_items_price,
                'total_amount' => $this->total_items_price,
                'order_status' => 'pending'
            ]);
            
            // Copy items to order
            foreach ($this->items as $cartItem) {
                $order->items()->create([
                    'product_id' => $cartItem->product_id,
                    'product_name' => $cartItem->product_name,
                    'unit_price' => $cartItem->unit_price,
                    'quantity' => $cartItem->quantity,
                    'item_total' => $cartItem->item_total
                ]);
                
                // Reserve inventory
                StockMovement::create([
                    'inventory_id' => $cartItem->product->inventory->id,
                    'product_id' => $cartItem->product_id,
                    'movement_type' => 'order_reserved',
                    'quantity_change' => -$cartItem->quantity,
                    'order_id' => $order->id,
                    'reason' => 'Reserved for order'
                ]);
            }
            
            // Mark cart as converted
            $this->update(['cart_status' => 'converted_to_order']);
            
            return $order;
        });
    }
}

// app/Models/Order.php (updated)
class Order extends Model {
    public function confirmOrder() {
        DB::transaction(function () {
            // Update status
            $this->update(['order_status' => 'confirmed', 'confirmed_at' => now()]);
            
            // Deduct actual inventory
            foreach ($this->items as $item) {
                StockMovement::create([
                    'inventory_id' => $item->product->inventory->id,
                    'product_id' => $item->product_id,
                    'movement_type' => 'order_release_from_reserved',
                    'quantity_change' => 0,
                    'order_id' => $this->id,
                    'reason' => 'Order confirmed'
                ]);
            }
            
            // Send notification
            event(new OrderConfirmed($this));
        });
    }
}

// OrderService.php
class OrderService {
    public function createOrderFromCart(Cart $cart, Payment $payment) {
        return DB::transaction(function () use ($cart, $payment) {
            // Verify payment
            if ($payment->payment_status !== 'captured') {
                throw new PaymentException('Payment not captured');
            }
            
            // Convert cart to order
            $order = $cart->convertToOrder();
            
            // Link payment
            $payment->update(['order_id' => $order->id]);
            
            // Send confirmation
            Notification::create([
                'recipient_id' => $order->vendor->user_id,
                'title' => 'Order Placed Successfully',
                'body' => 'Order #' . $order->order_number . ' has been placed',
                'notification_type' => 'order_placed',
                'entity_type' => 'order',
                'entity_id' => $order->id,
                'send_push' => true,
                'send_sms' => true
            ]);
            
            return $order;
        });
    }
}
```

#### API Endpoints for Stage 2:

```php
// Cart Management
POST   /api/v1/cart
GET    /api/v1/cart
POST   /api/v1/cart/items
PUT    /api/v1/cart/items/{itemId}
DELETE /api/v1/cart/items/{itemId}
POST   /api/v1/cart/clear

// Checkout
POST   /api/v1/orders/checkout
GET    /api/v1/orders/{id}
```

---

### STAGE 3: BACKEND ORDER PROCESSING (WAREHOUSE)

#### Workflow Requirement:
```
Order Received → Inventory Check → Dispatch Slip Generation → Status Updates
```

#### Database Implementation:

```
┌─────────────────────────────────────────────────────────────┐
│              STAGE 3 FLOW (WAREHOUSE)                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. Order Reception (orders)                               │
│     └─ Order arrives in warehouse system                   │
│     └─ Status: pending → processing                        │
│                                                              │
│  2. Inventory Verification (inventory + stock_movements)   │
│     └─ Real-time stock check                               │
│     └─ Track movements                                     │
│                                                              │
│  3. Dispatch Slip Creation (dispatch_slips - NEW)          │
│     └─ Auto-generate dispatch slip                         │
│     └─ Packing details                                     │
│     └─ Warehouse location tracking                         │
│                                                              │
│  4. Status Notifications (notifications)                  │
│     └─ Send vendor updates                                 │
│     └─ Processing → Ready for Dispatch                     │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

#### Key Tables for Stage 3:
```
1. orders
   - Status progression: pending → processing → ready_dispatch
   
2. order_items
   - Track items in order
   
3. dispatch_slips (NEW)
   - Generated for each order
   - Contains: packing_details, warehouse_location, tracking
   - Status: pending → approved → packed → handed_over
   
4. stock_movements
   - Log inventory deductions
   
5. notifications
   - Warehouse to vendor communication
   
6. admin_review_logs (NEW)
   - Track warehouse approvals
```

#### Laravel Implementation:

```php
// app/Models/DispatchSlip.php (NEW)
class DispatchSlip extends Model {
    protected $fillable = [
        'order_id', 'dispatch_slip_number', 'dispatch_status',
        'packing_details', 'warehouse_location'
    ];
    
    protected $casts = [
        'packing_details' => 'json'
    ];
    
    public function order() {
        return $this->belongsTo(Order::class);
    }
    
    public static function createForOrder(Order $order) {
        $dispatchSlip = self::create([
            'order_id' => $order->id,
            'dispatch_slip_number' => 'DSP-' . date('Y') . '-' . str_pad(self::max('id') + 1, 6, '0', STR_PAD_LEFT),
            'dispatch_status' => 'pending',
            'packing_details' => [
                'items' => $order->items->map(fn($item) => [
                    'product_name' => $item->product_name,
                    'sku' => $item->product_sku,
                    'quantity' => $item->quantity,
                    'weight_grams' => $item->product->weight_grams
                ]),
                'total_weight_grams' => $order->items->sum('product.weight_grams'),
                'total_items' => $order->items->sum('quantity')
            ],
            'warehouse_location' => 'RACK-A-15' // Example location
        ]);
        
        return $dispatchSlip;
    }
    
    public function approve() {
        $this->update(['dispatch_status' => 'approved']);
        
        // Send to warehouse team
        event(new DispatchSlipApproved($this));
    }
    
    public function markAsPacked() {
        $this->update([
            'dispatch_status' => 'packed',
            'packed_at' => now()
        ]);
        
        // Notify vendor
        Notification::create([
            'recipient_id' => $this->order->vendor->user_id,
            'title' => 'Order Packed',
            'body' => 'Your order #' . $this->order->order_number . ' has been packed',
            'notification_type' => 'order_packed',
            'entity_type' => 'order',
            'entity_id' => $this->order->id,
            'send_push' => true,
            'send_sms' => true
        ]);
    }
    
    public function handOverToLogistics($logisticsPartner, $trackingNumber) {
        $this->update([
            'dispatch_status' => 'handed_over',
            'handed_over_at' => now(),
            'logistics_partner' => $logisticsPartner,
            'tracking_number' => $trackingNumber
        ]);
        
        // Update order status
        $this->order->update(['order_status' => 'dispatched']);
        
        // Notify vendor with tracking
        Notification::create([
            'recipient_id' => $this->order->vendor->user_id,
            'title' => 'Order Dispatched',
            'body' => 'Your order #' . $this->order->order_number . ' is on the way',
            'notification_type' => 'order_dispatched',
            'entity_type' => 'order',
            'entity_id' => $this->order->id,
            'data' => [
                'tracking_number' => $trackingNumber,
                'logistics_partner' => $logisticsPartner
            ],
            'send_push' => true,
            'send_sms' => true
        ]);
    }
}

// WarehouseService.php (NEW)
class WarehouseService {
    public function processOrder(Order $order) {
        return DB::transaction(function () use ($order) {
            // 1. Verify inventory
            foreach ($order->items as $item) {
                $inventory = $item->product->inventory;
                
                if ($inventory->available_stock < $item->quantity) {
                    throw new InsufficientInventoryException(
                        "Insufficient stock for {$item->product_name}"
                    );
                }
            }
            
            // 2. Update order status
            $order->update(['order_status' => 'processing']);
            
            // 3. Create dispatch slip
            $dispatchSlip = DispatchSlip::createForOrder($order);
            
            // 4. Approve and mark as packed (automated)
            $dispatchSlip->approve();
            $dispatchSlip->markAsPacked();
            
            // 5. Log admin action
            AdminReviewLog::create([
                'admin_id' => auth()->id(),
                'entity_type' => 'order',
                'entity_id' => $order->id,
                'action' => 'approved',
                'review_comments' => 'Order approved and dispatch slip generated'
            ]);
            
            return $dispatchSlip;
        });
    }
}
```

#### Warehouse Admin API Endpoints:

```php
// Warehouse operations (admin only)
GET    /api/v1/admin/warehouse/orders/pending
POST   /api/v1/admin/warehouse/orders/{id}/process
GET    /api/v1/admin/warehouse/dispatch-slips
PUT    /api/v1/admin/warehouse/dispatch-slips/{id}/approve
PUT    /api/v1/admin/warehouse/dispatch-slips/{id}/pack
PUT    /api/v1/admin/warehouse/dispatch-slips/{id}/handover
```

---

### STAGE 4: DISPATCH & DELIVERY TRACKING

#### Workflow Requirement:
```
Package Handover → Live Tracking → Delivery Confirmation (OTP/QR) → Feedback
```

#### Database Implementation:

```
┌─────────────────────────────────────────────────────────────┐
│           STAGE 4 FLOW (DELIVERY & TRACKING)                │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. Delivery Assignment (deliveries)                       │
│     └─ Assign delivery partner                             │
│     └─ Create delivery record                              │
│                                                              │
│  2. Real-time Location Tracking (delivery_locations)       │
│     └─ GPS updates every 30 seconds                        │
│     └─ Store location history                              │
│     └─ Display on vendor app                               │
│                                                              │
│  3. OTP/QR Verification (delivery_verifications - NEW)    │
│     └─ Generate OTP                                        │
│     └─ Or use vendor QR code                               │
│     └─ Confirm delivery                                    │
│                                                              │
│  4. Proof of Delivery (delivery_verifications)             │
│     └─ Photo capture                                       │
│     └─ Signature (optional)                                │
│     └─ Recipient name                                      │
│                                                              │
│  5. Rating & Feedback (ratings)                           │
│     └─ Vendor rates delivery partner                       │
│     └─ Feedback on delivery experience                     │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

#### Key Tables for Stage 4:
```
1. deliveries
   - Main delivery record
   - Status progression: pending → assigned → picked_up → in_transit
                         → out_for_delivery → delivered
   
2. delivery_locations (HIGH-VOLUME)
   - Real-time GPS coordinates
   - Indexed heavily for performance
   
3. delivery_verifications (NEW)
   - OTP verification
   - QR code scanning
   - Photo/signature storage
   
4. ratings
   - Delivery partner ratings
   
5. notifications
   - Real-time delivery updates to vendor
   
6. vendor_qr_codes
   - For QR-based delivery confirmation
```

#### Laravel Implementation:

```php
// app/Models/Delivery.php
class Delivery extends Model {
    public function assign(User $deliveryPartner) {
        $this->update(['delivery_partner_id' => $deliveryPartner->id]);
        
        // Generate OTP
        $otp = rand(100000, 999999);
        
        $verification = DeliveryVerification::create([
            'delivery_id' => $this->id,
            'delivery_otp' => $otp,
            'otp_sent_at' => now()
        ]);
        
        // Send OTP to vendor
        SendOtpNotification::dispatch($this->order->vendor, $otp);
        
        event(new DeliveryAssigned($this));
    }
    
    public function updateLocation($latitude, $longitude, $accuracy = null) {
        // Don't wait for DB write - queue it
        RecordDeliveryLocation::dispatch(
            $this->id,
            $latitude,
            $longitude,
            $accuracy
        )->onQueue('locations');
        
        // Update current location immediately
        $this->update([
            'current_latitude' => $latitude,
            'current_longitude' => $longitude
        ]);
        
        // Broadcast for real-time tracking
        broadcast(new DeliveryLocationUpdated(
            $this->id,
            $this->order->vendor->user_id,
            $latitude,
            $longitude
        ));
    }
    
    public function confirmDelivery($method = 'otp') {
        // $method: 'otp', 'qr_code', 'photo', etc
        
        $verification = $this->verification;
        
        if ($method === 'otp') {
            if (!$verification->otp_verified) {
                throw new DeliveryVerificationException('OTP not verified');
            }
        }
        
        if ($method === 'qr_code') {
            if (!$verification->qr_verified) {
                throw new DeliveryVerificationException('QR not verified');
            }
        }
        
        // Mark as delivered
        $this->update([
            'delivery_status' => 'delivered',
            'actual_delivery_time' => now()
        ]);
        
        // Update order status
        $this->order->update(['order_status' => 'delivered']);
        
        // Notify vendor
        Notification::create([
            'recipient_id' => $this->order->vendor->user_id,
            'title' => 'Order Delivered',
            'body' => 'Order #' . $this->order->order_number . ' delivered successfully',
            'notification_type' => 'order_delivered',
            'entity_type' => 'delivery',
            'entity_id' => $this->id,
            'send_push' => true
        ]);
        
        event(new DeliveryCompleted($this));
    }
}

// app/Models/DeliveryVerification.php (NEW)
class DeliveryVerification extends Model {
    protected $fillable = [
        'delivery_id', 'delivery_otp', 'otp_sent_at', 'otp_verified',
        'qr_verified', 'signature_photo_url', 'delivery_photo_url'
    ];
    
    public function delivery() {
        return $this->belongsTo(Delivery::class);
    }
    
    public function verifyOtp($otp) {
        // Verify OTP input
        if ($otp != $this->delivery_otp) {
            $this->increment('otp_attempts');
            
            if ($this->otp_attempts > 3) {
                throw new OtpLockedException('Too many failed OTP attempts');
            }
            
            throw new InvalidOtpException('Invalid OTP');
        }
        
        // Valid OTP
        $this->update([
            'otp_verified' => true,
            'otp_verified_at' => now()
        ]);
    }
    
    public function verifyQrCode($vendorQrCodeId) {
        // Verify QR code
        $qrCode = VendorQrCode::findByQrCodeId($vendorQrCodeId);
        
        if (!$qrCode || $qrCode->vendor_id !== $this->delivery->order->vendor_id) {
            throw new InvalidQrCodeException('Invalid or mismatched QR code');
        }
        
        // Log QR scan
        QrScanLog::create([
            'vendor_qr_code_id' => $qrCode->id,
            'scan_context' => 'delivery_verification',
            'scanned_by' => auth()->id(),
            'related_entity_type' => 'delivery',
            'related_entity_id' => $this->delivery->id
        ]);
        
        // Mark as verified
        $this->update([
            'qr_verified' => true,
            'qr_verified_at' => now(),
            'vendor_qr_code_id' => $qrCode->id
        ]);
    }
    
    public function addProofPhoto($photoUrl) {
        $this->update(['delivery_photo_url' => $photoUrl]);
    }
    
    public function addSignature($signatureUrl) {
        $this->update(['signature_photo_url' => $signatureUrl]);
    }
}

// Jobs/RecordDeliveryLocation.php
class RecordDeliveryLocation implements ShouldQueue {
    public function handle() {
        // Batched insert for performance
        // This reduces database load when many locations are recorded
        
        $locations = Redis::lpop('location_buffer', 1000);
        
        if ($locations) {
            DeliveryLocation::insert($locations);
        }
    }
}

// DeliveryService.php
class DeliveryService {
    public function createForOrder(Order $order) {
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'delivery_status' => 'pending'
        ]);
        
        return $delivery;
    }
}
```

#### Delivery Tracking API Endpoints:

```php
// Vendor - Track delivery
GET    /api/v1/deliveries/{id}/track          // Get live delivery info
GET    /api/v1/deliveries/{id}/location       // Current location
GET    /api/v1/deliveries/{id}/route-history  // Full route

// Delivery Partner - Update location
POST   /api/v1/deliveries/{id}/location       // Update GPS
POST   /api/v1/deliveries/{id}/verify-otp     // Verify OTP
POST   /api/v1/deliveries/{id}/verify-qr      // Scan QR code
POST   /api/v1/deliveries/{id}/confirm        // Mark delivered

// Vendor - Post-delivery
POST   /api/v1/deliveries/{id}/feedback       // Rate & feedback
POST   /api/v1/orders/{id}/rating             // Rate delivery
```

---

### STAGE 5: FUTURE ENHANCEMENTS

#### 5.1 AI VENDOR SCORING

**Tables: vendor_scores**

```php
// AI-based vendor grading (A-F)
// Automated daily calculation

// Metrics tracked:
// - On-time delivery %
// - Order fulfillment %
// - Average rating
// - Successful orders vs cancellations
// - Carbon footprint
// - Compliance adherence

// Grade Formula:
// A (90-100): Excellent vendor
// B (80-89): Good vendor
// C (70-79): Average vendor
// D (60-69): Below average
// F (<60): Poor vendor
```

#### 5.2 BLOCKCHAIN VERIFICATION

**Tables: blockchain_verifications**

```php
// Record order authenticity on blockchain
// Supports multiple networks: Ethereum, Polygon, Hyperledger

// Workflow:
// 1. Order completed
// 2. Hash order data
// 3. Record on blockchain
// 4. Store blockchain hash in DB
// 5. Vendor can verify authenticity anytime

// Use case: Prevent counterfeits, ensure supply chain transparency
```

#### 5.3 CARBON TRACKING DASHBOARD

**Tables: carbon_emissions, carbon_analytics**

```php
// Track carbon footprint per order
// Measure per delivery vehicle
// Calculate per vendor

// Metrics:
// - Transportation emissions
// - Packaging emissions
// - Warehouse operations
// - Product lifecycle
// - Returns processing

// Features:
// - Daily dashboard
// - Carbon reduction targets
// - Offset marketplace integration
// - Vendor sustainability scoring
```

#### 5.4 LOYALTY & REWARD POINTS SYSTEM

**Tables: vendor_loyalty_accounts, loyalty_points_ledger, loyalty_rewards_catalog, loyalty_redemptions**

```php
// Tier system:
// Bronze → Silver → Gold → Platinum

// Points earned:
// - For each order placed
// - For successful completion
// - For referrals
// - For reviews/feedback
// - For sustainability achievements

// Rewards:
// - Discount vouchers
// - Free shipping
// - Product credits
// - Feature upgrades
// - Commission boosts
// - Featured listings

// Redemption:
// - Exchange points for rewards
// - Apply discounts to orders
```

---

## 📊 Complete Database Relationship Map

```
users (1) ──► (Many) vendors
          ──► (Many) deliveries  (as delivery_partner)
          ──► (Many) admin_review_logs
                
vendors (1) ──► (Many) products
            ──► (Many) orders
            ──► (Many) carts
            ──► (1) vendor_settings
            ──► (Many) vendor_kyc_documents
            ──► (1) vendor_approvals
            ──► (Many) vendor_qr_codes
            ──► (1) vendor_scores
            ──► (1) vendor_loyalty_accounts
            ──► (Many) carbon_emissions
            ──► (Many) carbon_analytics
            ──► (Many) ratings (as vendor)

carts (1) ──► (Many) cart_items ──► products

orders (1) ──► (Many) order_items ──► products
          ──► (1) delivery
          ──► (1) payment
          ──► (1) invoice
          ──► (Many) ratings
          ──► (1) refund
          ──► (1) return
          ──► (1) dispatch_slip
          ──► (Many) carbon_emissions
          ──► (Many) blockchain_verifications

deliveries (1) ──► (Many) delivery_locations
            ──► (1) delivery_verification
            ──► (Many) ratings
            ──► (Many) carbon_emissions

payments (1) ──► (1) refund
          ──► (Many) blockchain_verifications

ratings ──► (Polymorphic) vendors, products, delivery_partners
```

---

## 🔄 Key Workflows Summary

| Stage | Tables | Status | Operations |
|-------|--------|--------|------------|
| **1. Onboarding** | users, vendors, vendor_kyc_documents, vendor_approvals, vendor_qr_codes | pending_documents → approved | KYC upload, admin review, QR generation |
| **2. Ordering** | carts, cart_items, orders, order_items, payments, stock_movements | pending → confirmed → processing | Browse, cart, checkout, payment |
| **3. Processing** | orders, dispatch_slips, notifications, admin_review_logs | processing → ready_dispatch | Inventory check, dispatch slip, packing |
| **4. Delivery** | deliveries, delivery_locations, delivery_verifications, notifications, ratings | pending → assigned → delivered | Location tracking, OTP/QR verification, feedback |
| **5. Analytics** | vendor_scores, vendor_daily_stats, carbon_emissions, loyalty_accounts | Computed daily | AI scoring, carbon tracking, loyalty points |

---

## 🚀 Implementation Priority

### Phase 1 (MVP) - Essential Workflow
- ✅ Users & Vendors (onboarding with QR)
- ✅ Products & Inventory
- ✅ Carts & Ordering
- ✅ Payments
- ✅ Dispatch Slips
- ✅ Deliveries & Tracking
- ✅ Delivery Verification (OTP/QR)
- ✅ Notifications

### Phase 2 (Growth) - Enhanced Features
- ✅ Vendor Approval Workflow
- ✅ Vendor Settings
- ✅ Refunds & Returns
- ✅ Ratings System
- ✅ QR Scan Logs

### Phase 3 (Scale) - Future Enhancements
- ✅ AI Vendor Scoring
- ✅ Blockchain Verification
- ✅ Carbon Tracking Dashboard
- ✅ Loyalty Reward Points System

---

## 📝 File Checklist

You now have:

1. ✅ **laravel_migrations_updated.php** - All new migrations aligned with workflow
2. ✅ **This document** - Complete workflow-to-database mapping
3. ✅ **Previous schema files** - Original tables (all still relevant)

---

## 🎯 Next Steps

1. **Copy migrations** to `database/migrations/`
2. **Create models** for new tables
3. **Define relationships** in models
4. **Implement services** for each workflow stage
5. **Build API endpoints**
6. **Test each workflow**

---

## 💡 Key Implementation Tips

### Workflow Progression
```php
// Always ensure status progression follows workflow
// DON'T allow: pending → delivered (skip processing)
// DO allow: pending → confirmed → processing → dispatched → delivered

protected $statusProgression = [
    'pending' => ['confirmed'],
    'confirmed' => ['processing'],
    'processing' => ['ready_dispatch'],
    'ready_dispatch' => ['dispatched'],
    'dispatched' => ['in_transit'],
    'in_transit' => ['out_for_delivery'],
    'out_for_delivery' => ['delivered'],
    'delivered' => []
];
```

### Inventory Safety
```php
// Reserve inventory when order placed
// Only deduct when order confirmed
// Handle refunds properly

StockMovement::create([
    'movement_type' => 'order_reserved',  // Don't deduct yet
    'quantity_change' => -$quantity
]);

// Later when order confirmed:
StockMovement::create([
    'movement_type' => 'order_confirmed',
    'quantity_change' => 0  // Already reserved
]);
```

### Notifications
```php
// Always send notifications for status changes
// Especially:
// - Order placed
// - Order packed
// - Order dispatched (with tracking)
// - Order delivered

// Use queues to not block request
Notification::dispatch($vendor, $message)->onQueue('notifications');
```

---

**Document Version**: 1.0
**Last Updated**: January 2024
**Status**: Production Ready
