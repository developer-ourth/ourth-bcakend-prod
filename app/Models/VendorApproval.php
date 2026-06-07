<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'approval_stage',
        'reviewed_by',
        'reviewed_at',
        'address_verified_by',
        'address_verified_at',
        'rejection_reason',
        'rejection_notes',
        'approval_notes',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'address_verified_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function addressVerifier()
    {
        return $this->belongsTo(User::class, 'address_verified_by');
    }

    public function reviewLogs()
    {
        return $this->hasMany(AdminReviewLog::class, 'entity_id')
            ->where('entity_type', 'vendor');
    }

    public function submitDocuments(): void
    {
        $this->update([
            'approval_stage' => 'documents_submitted',
            'submitted_at' => now(),
        ]);
    }

    public function startReview(): void
    {
        $this->update(['approval_stage' => 'under_review']);
    }

    public function requestAddressVerification(): void
    {
        $this->update(['approval_stage' => 'address_verification']);
    }

    public function approve(): void
    {
        $this->update([
            'approval_stage' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);
    }

    public function reject(string $reason, ?string $notes = null): void
    {
        $this->update([
            'approval_stage' => 'rejected',
            'rejection_reason' => $reason,
            'rejection_notes' => $notes,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);
    }

    public function verifyAddress(): void
    {
        $this->update([
            'address_verified_at' => now(),
            'address_verified_by' => auth()->id(),
        ]);
    }
}
