<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSettings extends Model
{
    protected $fillable = [
        'vendor_id',
        'accept_orders',
        'auto_accept_orders',
        'minimum_order_value',
        'delivery_charge',
        'free_delivery_enabled',
        'free_delivery_above',
        'notification_email',
        'notification_phone',
        'sms_notifications',
        'email_notifications',
        'push_notifications',
        'operating_hours',
        'holiday_mode',
    ];

    protected function casts(): array
    {
        return [
            'accept_orders' => 'boolean',
            'auto_accept_orders' => 'boolean',
            'minimum_order_value' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'free_delivery_enabled' => 'boolean',
            'free_delivery_above' => 'decimal:2',
            'sms_notifications' => 'boolean',
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'operating_hours' => 'array',
            'holiday_mode' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
