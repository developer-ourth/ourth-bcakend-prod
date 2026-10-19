<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phone normalisation shared by every CRM lead source.
 *
 * Registration and OTP login store Indian numbers as 10 digits, so leads are
 * stored the same way to avoid creating a duplicate user for the same person.
 */
class PhoneNumber
{
    /**
     * Normalise to the format stored in users.phone: 10 digits for Indian
     * numbers, bare digits (with country code) for everything else.
     */
    public static function normalize(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }

    /**
     * Format for WhatsApp / Meta APIs: digits with country code, no "+".
     */
    public static function toE164Digits(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        if (! $normalized) {
            return null;
        }

        return strlen($normalized) === 10 ? '91' . $normalized : $normalized;
    }

    /**
     * Find the user that owns this phone number, whatever format it was stored in.
     */
    public static function findUser(?string $phone): ?User
    {
        $normalized = self::normalize($phone);

        if (! $normalized) {
            return null;
        }

        return User::query()
            ->where(function (Builder $query) use ($normalized) {
                $query->where('phone', $normalized)
                    ->orWhere('phone', '91' . $normalized)
                    ->orWhere('phone', '+91' . $normalized)
                    ->orWhere('phone', 'like', '%' . $normalized);
            })
            ->orderBy('id')
            ->first();
    }
}
