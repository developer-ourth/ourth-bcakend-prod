<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'app_background_color', 'value' => '#FAF8F3'],
            ['key' => 'header_background_color', 'value' => 'rgba(13,58,39,0.85)'],
            ['key' => 'banner_tagline', 'value' => 'Healing OURTH Tableware'],
            ['key' => 'banner_subtagline', 'value' => '100% Organic, Natural & Compostable'],
        ];

        foreach ($settings as $setting) {
            \App\Models\AppSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
