<?php

namespace App\Services\Zoho;

/**
 * Zoho's 2-letter codes for Indian states / UTs (place_of_supply, place_of_contact).
 * Source: https://www.zoho.com/billing/api/v1/country-codes/ (Andhra Pradesh uses
 * "AP" as in the Zoho Books API examples).
 */
class IndianStates
{
    private const CODES = [
        'andaman and nicobar islands' => 'AN', 'andhra pradesh' => 'AP', 'arunachal pradesh' => 'AR',
        'assam' => 'AS', 'bihar' => 'BR', 'chandigarh' => 'CH', 'chhattisgarh' => 'CG',
        'dadra and nagar haveli and daman and diu' => 'DN', 'dadra and nagar haveli' => 'DN', 'daman and diu' => 'DD',
        'delhi' => 'DL', 'new delhi' => 'DL', 'nct of delhi' => 'DL', 'goa' => 'GA', 'gujarat' => 'GJ',
        'haryana' => 'HR', 'himachal pradesh' => 'HP', 'jammu and kashmir' => 'JK', 'jharkhand' => 'JH',
        'karnataka' => 'KA', 'kerala' => 'KL', 'ladakh' => 'LA', 'lakshadweep' => 'LD', 'madhya pradesh' => 'MP',
        'maharashtra' => 'MH', 'manipur' => 'MN', 'meghalaya' => 'ML', 'mizoram' => 'MZ', 'nagaland' => 'NL',
        'odisha' => 'OD', 'orissa' => 'OD', 'puducherry' => 'PY', 'pondicherry' => 'PY', 'punjab' => 'PB',
        'rajasthan' => 'RJ', 'sikkim' => 'SK', 'tamil nadu' => 'TN', 'telangana' => 'TS', 'tripura' => 'TR',
        'uttar pradesh' => 'UP', 'uttarakhand' => 'UK', 'uttaranchal' => 'UK', 'west bengal' => 'WB',
    ];

    public static function code(?string $state): ?string
    {
        $key = strtolower(trim(preg_replace('/\s+/', ' ', str_replace('&', 'and', (string) $state))));

        if (strlen($key) === 2 && in_array(strtoupper($key), self::CODES, true)) {
            return strtoupper($key);
        }

        return self::CODES[$key] ?? null;
    }
}
