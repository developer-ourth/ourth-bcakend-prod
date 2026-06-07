<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dustbin extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'qr_code',
        'bin_label',
        'city',
        'area',
        'location_description',
        'latitude',
        'longitude',
        'bin_type',
        'capacity_litres',
        'fill_level_percent',
        'status',
        'assigned_vendor_id',
        'last_emptied_at',
        'last_scanned_at',
        'total_scans',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'last_emptied_at' => 'datetime',
            'last_scanned_at' => 'datetime',
        ];
    }

    public function assignedVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'assigned_vendor_id');
    }

    public function wasteCollections(): HasMany
    {
        return $this->hasMany(WasteCollection::class);
    }

    public function isFull(): bool
    {
        return $this->fill_level_percent >= 90;
    }
}
