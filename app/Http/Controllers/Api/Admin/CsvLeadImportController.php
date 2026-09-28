<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\AttributionTouchpoint;

class CsvLeadImportController extends Controller
{
    /**
     * E.164 phone formatting helper
     */
    protected function formatPhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 10) {
            return '91' . $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }
        return strlen($digits) >= 10 ? $digits : null;
    }

    /**
     * Bulk Import Leads from CSV File
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        try {
            $file = $request->file('csv_file');
            $handle = fopen($file->getRealPath(), 'r');

            if (!$handle) {
                return response()->json(['status' => 'error', 'message' => 'Unable to read uploaded CSV file.'], 400);
            }

            $importedCount = 0;
            $duplicatesCount = 0;
            $headerSkipped = false;

            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if (empty(array_filter($data))) continue;

                // Check if row 1 is header
                if (!$headerSkipped) {
                    $headerSkipped = true;
                    $firstCol = strtolower(trim($data[0] ?? ''));
                    if (str_contains($firstCol, 'name') || str_contains($firstCol, 'phone') || str_contains($firstCol, 'contact')) {
                        continue;
                    }
                }

                $name = trim($data[0] ?? 'CSV Prospect');
                $rawPhone = trim($data[1] ?? ($data[0] ?? ''));
                $city = trim($data[2] ?? 'India');
                $segment = trim($data[3] ?? 'B2B_Distributor');

                // If phone is in col 0 and name is empty
                if (preg_match('/^\+?\d{10,14}$/', preg_replace('/\D/', '', $name))) {
                    $rawPhone = $name;
                    $name = 'Bulk Lead (' . substr(preg_replace('/\D/', '', $rawPhone), -4) . ')';
                }

                $phone = $this->formatPhone($rawPhone);
                if (!$phone) continue;

                $user = User::where('phone', $phone)->first();
                if (!$user) {
                    $user = User::create([
                        'name' => $name,
                        'phone' => $phone,
                        'email' => 'csv_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . rand(100, 999) . '@lead.healingourth.com',
                        'user_type' => in_array($segment, ['B2C', 'Vendor']) ? $segment : 'B2B_Distributor',
                        'password' => bcrypt('LeadSecret2026!'),
                    ]);

                    AttributionTouchpoint::create([
                        'user_id' => $user->id,
                        'source_type' => 'csv_bulk_import',
                        'raw_payload' => ['city' => $city, 'imported_at' => now()->toIso8601String()],
                    ]);

                    $importedCount++;
                } else {
                    $duplicatesCount++;
                }
            }

            fclose($handle);

            return response()->json([
                'status' => 'success',
                'message' => "Successfully imported {$importedCount} new leads from CSV ({$duplicatesCount} duplicates skipped)!",
                'imported_count' => $importedCount,
                'duplicates_count' => $duplicatesCount,
            ]);
        } catch (\Exception $e) {
            Log::error('CSV Lead Import Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to process CSV file: ' . $e->getMessage()], 500);
        }
    }
}
