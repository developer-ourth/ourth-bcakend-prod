<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'is_distributor',
        'vendor_code',
        'business_name',
        'business_category',
        'description',
        'logo_url',
        'gstin',
        'pan',
        'trade_license_number',
        'trade_license_expiry',
        'bank_account_number',
        'bank_ifsc_code',
        'bank_account_holder_name',
        'kyc_status',
        'kyc_verified_at',
        'kyc_verified_by',
        'kyc_rejection_reason',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'average_rating',
        'total_ratings_count',
        'qr_code_id',
        'total_orders',
        'total_revenue',
    ];

    /**
     * Retrieve the single Ourth distributor vendor.
     */
    public static function distributor(): ?self
    {
        return self::where('is_distributor', true)->first();
    }

    protected function casts(): array
    {
        return [
            'is_distributor' => 'boolean',
            'kyc_verified_at' => 'datetime',
            'trade_license_expiry' => 'date',
            'average_rating' => 'float',
            'total_revenue' => 'decimal:2',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function kycDocuments()
    {
        return $this->hasMany(VendorKycDocument::class);
    }

    public function settings()
    {
        return $this->hasOne(VendorSettings::class);
    }

    public function approval()
    {
        return $this->hasOne(VendorApproval::class);
    }

    public function qrCodes()
    {
        return $this->hasMany(VendorQrCode::class);
    }

    public function activeQrCode()
    {
        return $this->hasOne(VendorQrCode::class)
            ->where('status', 'active')
            ->latestOfMany();
    }

    public function ratings()
    {
        return $this->morphMany(Rating::class, 'ratable');
    }

    public function dailyStats()
    {
        return $this->hasMany(VendorDailyStat::class);
    }

    public function scopeVerified($query)
    {
        return $query->where('kyc_status', 'verified');
    }

    public function scopeApproved($query)
    {
        return $query->whereHas('approval', function ($q) {
            $q->where('approval_stage', 'approved');
        });
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function getFullAddressAttribute(): string
    {
        return "{$this->address_line1}, {$this->city}, {$this->state} {$this->postal_code}";
    }

    public function isKycVerified(): bool
    {
        return $this->kyc_status === 'verified';
    }

    public function isApproved(): bool
    {
        return $this->approval?->approval_stage === 'approved';
    }

    public function updateAverageRating(): void
    {
        $average = $this->ratings()->avg('rating') ?? 0;
        $count = $this->ratings()->count();

        $this->update([
            'average_rating' => $average,
            'total_ratings_count' => $count,
        ]);
    }
}
