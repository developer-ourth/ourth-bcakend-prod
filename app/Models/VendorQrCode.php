<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorQrCode extends Model
{
    protected $fillable = [
        'vendor_id',
        'qr_code_id',
        'qr_code_image_url',
        'status',
        'generated_at',
        'scans_count',
        'last_scanned_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'last_scanned_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
