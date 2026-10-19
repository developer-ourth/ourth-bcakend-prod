<?php

namespace App\Services;

use App\Jobs\SyncLeadToKrayin;
use App\Models\AttributionTouchpoint;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Single entry point for every lead source (Meta Lead Ads, WhatsApp, CSV,
 * Google Places). Finds or creates the user, records the attribution
 * touchpoint and queues the sync into Krayin CRM.
 */
class LeadCaptureService
{
    public const SEGMENTS = ['B2C', 'B2B_Distributor', 'Vendor'];

    /** Krayin lead source name per touchpoint source_type. */
    public const KRAYIN_SOURCES = [
        'meta_lead_ad' => 'Meta Lead Ad',
        'whatsapp_ctwa' => 'WhatsApp Ad (CTWA)',
        'whatsapp_inbound' => 'WhatsApp',
        'csv_bulk_import' => 'CSV Import',
        'google_places_lead' => 'Google Places',
        'website_order' => 'Website Order',
    ];

    /**
     * @param  array  $touchpoint  extra attribution_touchpoints columns (ad_id, campaign_id, raw_payload, ...)
     * @return array{user: User, created: bool}|null  null when the phone number is unusable
     */
    public function capture(
        ?string $phone,
        ?string $name,
        string $sourceType,
        string $segment = 'B2C',
        ?string $email = null,
        array $touchpoint = [],
        string $note = '',
    ): ?array {
        $normalizedPhone = PhoneNumber::normalize($phone);

        if (! $normalizedPhone) {
            return null;
        }

        $segment = in_array($segment, self::SEGMENTS, true) ? $segment : 'B2C';
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;

        [$user, $created] = DB::transaction(function () use ($normalizedPhone, $name, $segment, $email, $sourceType, $touchpoint) {
            $user = PhoneNumber::findUser($normalizedPhone);
            $created = false;

            if (! $user) {
                // Leads are not given a usable password: they sign in with OTP like everyone else.
                $user = User::create([
                    'name' => $name ?: 'Lead ' . substr($normalizedPhone, -4),
                    'phone' => $normalizedPhone,
                    'email' => $email && ! User::where('email', $email)->exists() ? $email : null,
                    'password' => Hash::make(Str::random(40)),
                    'crm_segment' => $segment,
                ]);
                $created = true;
            } elseif (! $user->crm_segment) {
                $user->update(['crm_segment' => $segment]);
            }

            AttributionTouchpoint::create(array_merge($touchpoint, [
                'user_id' => $user->id,
                'source_type' => $sourceType,
            ]));

            return [$user, $created];
        });

        SyncLeadToKrayin::dispatch(
            $user->id,
            self::KRAYIN_SOURCES[$sourceType] ?? 'Other',
            $note,
        )->afterCommit();

        return ['user' => $user, 'created' => $created];
    }
}
