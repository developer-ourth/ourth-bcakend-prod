<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrScanLog extends Model
{
    protected $fillable = [
        'vendor_qr_code_id',
        'scan_context',
        'scanned_by',
        'related_entity_type',
        'related_entity_id',
        'ip_address',
        'user_agent',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function vendorQrCode(): BelongsTo
    {
        return $this->belongsTo(VendorQrCode::class);
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
