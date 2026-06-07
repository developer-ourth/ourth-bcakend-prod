<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('categories')
            ->whereNotNull('icon_url')
            ->where('icon_url', 'like', '%/storage/%')
            ->get(['id', 'icon_url'])
            ->each(function (object $row): void {
                $fixed = preg_replace(
                    '#^https?://[^/]+/storage/#',
                    rtrim(config('app.url'), '/').'/api/v1/media/',
                    $row->icon_url
                );

                DB::table('categories')
                    ->where('id', $row->id)
                    ->update(['icon_url' => $fixed]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('categories')
            ->whereNotNull('icon_url')
            ->where('icon_url', 'like', '%/api/v1/media/%')
            ->get(['id', 'icon_url'])
            ->each(function (object $row): void {
                $fixed = preg_replace(
                    '#^https?://[^/]+/api/v1/media/#',
                    rtrim(config('app.url'), '/').'/storage/',
                    $row->icon_url
                );

                DB::table('categories')
                    ->where('id', $row->id)
                    ->update(['icon_url' => $fixed]);
            });
    }
};
