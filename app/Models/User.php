<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'user_type',
        'status',
        'role',
        'last_login_at',
        'last_ip_address',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function vendor()
    {
        return $this->hasOne(Vendor::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function rewardTransactions()
    {
        return $this->hasMany(RewardTransaction::class);
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function deliveryOrders()
    {
        return $this->hasMany(Delivery::class, 'delivery_partner_id');
    }

    public function adminReviews()
    {
        return $this->hasMany(AdminReviewLog::class, 'admin_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function scopeVendor($query)
    {
        return $query->where('user_type', 'vendor');
    }

    public function scopeDeliveryPartner($query)
    {
        return $query->where('user_type', 'delivery_partner');
    }

    public function scopeAdmin($query)
    {
        return $query->where('user_type', 'admin');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isVendor(): bool
    {
        return $this->user_type === 'vendor';
    }

    public function isDeliveryPartner(): bool
    {
        return $this->user_type === 'delivery_partner';
    }

    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    public function updateLastLogin(): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_ip_address' => request()->ip(),
        ]);
    }
}
