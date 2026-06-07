<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->whereNotNull('icon_url')
            ->where('icon_url', 'like', 'http://localhost%')
            ->get(['id', 'icon_url'])
            ->each(function ($row) {
                $fixed = preg_replace('#^http://localhost(:\d+)?#', config('app.url'), $row->icon_url);
                DB::table('categories')->where('id', $row->id)->update(['icon_url' => $fixed]);
            });
    }

    public function down(): void
    {
        //
    }
};
