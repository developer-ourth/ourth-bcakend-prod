<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    protected ?string $apiKey;

    public function __construct(protected LeadCaptureService $leads)
    {
        $this->apiKey = config('services.google.places_api_key')
            ?: (AppSetting::where('key', 'google_places_api_key')->value('value') ?: env('GOOGLE_PLACES_API_KEY'));
    }

    /**
     * Discover B2B businesses via Google Places and add those with a listed phone number as leads.
     */
    public function discoverAndIngestLeads(string $keyword, string $city = 'Mumbai'): array
    {
        if (!$this->apiKey) {
            return [
                'success' => false,
                'message' => 'Google Places API key is not configured. Add it in Website Settings → Google Places API Key.',
                'new_leads_count' => 0,
            ];
        }

        $queryText = trim("{$keyword} in {$city}");
        Log::info("Google Places Lead Discovery initiated for: '{$queryText}'");

        try {
            $response = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/place/textsearch/json', [
                'query' => $queryText,
                'key' => $this->apiKey,
            ]);

            $status = $response->json('status');
            if (!$response->successful() || !in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
                Log::error('Google Places API error: ' . $response->body());
                return [
                    'success' => false,
                    'message' => 'Google Places API error: ' . ($response->json('error_message') ?? $status ?? $response->status()),
                    'new_leads_count' => 0,
                ];
            }

            $importedCount = 0;
            $duplicatesCount = 0;
            $noPhoneCount = 0;
            $importedLeads = [];

            foreach (array_slice($response->json('results') ?? [], 0, 15) as $place) {
                $placeId = $place['place_id'] ?? null;
                $businessName = $place['name'] ?? 'B2B Lead';

                $details = $placeId ? Http::timeout(15)->get('https://maps.googleapis.com/maps/api/place/details/json', [
                    'place_id' => $placeId,
                    'fields' => 'name,formatted_phone_number,international_phone_number,website',
                    'key' => $this->apiKey,
                ])->json('result') ?? [] : [];

                $phone = PhoneNumber::normalize($details['international_phone_number'] ?? $details['formatted_phone_number'] ?? null);

                // Businesses without a listed number cannot be contacted; never invent one.
                if (!$phone) {
                    $noPhoneCount++;
                    continue;
                }

                $result = $this->leads->capture(
                    phone: $phone,
                    name: $businessName,
                    sourceType: 'google_places_lead',
                    segment: 'B2B_Distributor',
                    touchpoint: [
                        'ad_id' => $placeId,
                        'form_id' => mb_substr($keyword, 0, 100),
                        'raw_payload' => [
                            'business_name' => $businessName,
                            'address' => $place['formatted_address'] ?? $city,
                            'rating' => $place['rating'] ?? null,
                            'website' => $details['website'] ?? null,
                            'city' => $city,
                        ],
                    ],
                    note: implode("\n", array_filter([
                        "Found via Google Places search: {$queryText}",
                        'Address: ' . ($place['formatted_address'] ?? $city),
                        !empty($details['website']) ? "Website: {$details['website']}" : null,
                        isset($place['rating']) ? "Google rating: {$place['rating']}" : null,
                    ])),
                );

                if ($result && $result['created']) {
                    $importedCount++;
                    $importedLeads[] = [
                        'id' => $result['user']->id,
                        'name' => $businessName,
                        'phone_number' => $phone,
                        'segment' => 'B2B_Distributor',
                        'city' => $city,
                    ];
                } else {
                    $duplicatesCount++;
                }
            }

            return [
                'success' => true,
                'message' => "Imported {$importedCount} new B2B leads for {$city} ({$duplicatesCount} already known, {$noPhoneCount} without a listed phone skipped).",
                'new_leads_count' => $importedCount,
                'duplicates_count' => $duplicatesCount,
                'data' => $importedLeads,
            ];
        } catch (\Exception $e) {
            Log::error('Google Places Service Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error discovering leads: ' . $e->getMessage(), 'new_leads_count' => 0];
        }
    }
}
