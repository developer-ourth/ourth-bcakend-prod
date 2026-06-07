<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorKycDocument extends Model
{
    protected $fillable = [
        'vendor_id',
        'document_type',
        'document_url',
        'status',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
