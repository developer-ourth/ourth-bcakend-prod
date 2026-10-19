<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\LeadCaptureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CsvLeadImportController extends Controller
{
    private const MAX_ROWS = 5000;

    /**
     * Bulk import leads from a CSV file.
     * Columns: Name, Phone, City, Segment (B2C / B2B_Distributor / Vendor). A header row is optional.
     */
    public function import(Request $request, LeadCaptureService $leads)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');

        if (!$handle) {
            return response()->json(['status' => 'error', 'message' => 'Unable to read uploaded CSV file.'], 400);
        }

        $importedCount = 0;
        $duplicatesCount = 0;
        $invalidCount = 0;
        $rowNumber = 0;

        try {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if (empty(array_filter($data))) {
                    continue;
                }

                $rowNumber++;

                if ($rowNumber > self::MAX_ROWS) {
                    break;
                }

                // Skip a header row
                if ($rowNumber === 1) {
                    $firstCol = strtolower(trim($data[0] ?? ''));
                    if (str_contains($firstCol, 'name') || str_contains($firstCol, 'phone') || str_contains($firstCol, 'contact')) {
                        continue;
                    }
                }

                $name = trim($data[0] ?? '');
                $rawPhone = trim($data[1] ?? '');
                $city = trim($data[2] ?? '');
                $segment = trim($data[3] ?? '') ?: 'B2B_Distributor';

                // Single-column files: the phone number is in column 1
                if ($rawPhone === '' && preg_match('/^\+?[\d\s-]{10,16}$/', $name)) {
                    $rawPhone = $name;
                    $name = '';
                }

                $result = $leads->capture(
                    phone: $rawPhone,
                    name: $name ?: null,
                    sourceType: 'csv_bulk_import',
                    segment: $segment,
                    touchpoint: [
                        'raw_payload' => ['city' => $city ?: null, 'file' => $request->file('csv_file')->getClientOriginalName()],
                    ],
                    note: $city ? "Imported from CSV. City: {$city}" : 'Imported from CSV.',
                );

                if (!$result) {
                    $invalidCount++;
                } elseif ($result['created']) {
                    $importedCount++;
                } else {
                    $duplicatesCount++;
                }
            }
        } catch (\Exception $e) {
            Log::error('CSV Lead Import Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to process CSV file: ' . $e->getMessage()], 500);
        } finally {
            fclose($handle);
        }

        $message = "Imported {$importedCount} new leads ({$duplicatesCount} already known, {$invalidCount} rows with an invalid phone skipped).";
        if ($rowNumber > self::MAX_ROWS) {
            $message .= ' Only the first ' . self::MAX_ROWS . ' rows were processed.';
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'imported_count' => $importedCount,
            'duplicates_count' => $duplicatesCount,
            'invalid_count' => $invalidCount,
        ]);
    }
}
