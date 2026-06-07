<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rewrite all stored asset URLs from the old Apache/XAMPP path
     * (http://localhost/ourth-app/public) to the artisan-serve path
     * (http://localhost:8000) so images resolve correctly.
     */
    public function up(): void
    {
        $old = 'http://localhost/ourth-app/public';
        $new = 'http://localhost:8000';

        $replacements = [
            'products' => ['primary_image_url'],
            'vendors' => ['logo_url'],
            'categories' => ['icon_url'],
            'reward_catalog' => ['image_url'],
            'vendor_qr_codes' => ['qr_code_image_url'],
            'deliveries' => ['proof_of_delivery_url'],
            'delivery_verifications' => ['verification_image_url'],
            'invoices' => ['invoice_pdf_url'],
            'recycling_records' => ['certificate_url'],
            'vendor_kyc_documents' => ['document_url'],
            'waste_collections' => ['photo_url'],
        ];

        foreach ($replacements as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(
                    "UPDATE {$table} SET {$column} = REPLACE({$column}, ?, ?) WHERE {$column} LIKE ?",
                    [$old, $new, $old.'%']
                );
            }
        }
    }

    public function down(): void
    {
        $old = 'http://localhost:8000';
        $new = 'http://localhost/ourth-app/public';

        $replacements = [
            'products' => ['primary_image_url'],
            'vendors' => ['logo_url'],
            'categories' => ['icon_url'],
            'reward_catalog' => ['image_url'],
            'vendor_qr_codes' => ['qr_code_image_url'],
            'deliveries' => ['proof_of_delivery_url'],
            'delivery_verifications' => ['verification_image_url'],
            'invoices' => ['invoice_pdf_url'],
            'recycling_records' => ['certificate_url'],
            'vendor_kyc_documents' => ['document_url'],
            'waste_collections' => ['photo_url'],
        ];

        foreach ($replacements as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(
                    "UPDATE {$table} SET {$column} = REPLACE({$column}, ?, ?) WHERE {$column} LIKE ?",
                    [$old, $new, $old.'%']
                );
            }
        }
    }
};
