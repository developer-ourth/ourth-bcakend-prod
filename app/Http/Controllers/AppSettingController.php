<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    /**
     * Get all app settings (public/user).
     */
    public function index()
    {
        $settings = AppSetting::pluck('value', 'key');
        
        // Provide defaults if missing from DB entirely
        if (!$settings->has('app_background_color')) {
            $settings['app_background_color'] = '#FAF8F3';
        }
        if (!$settings->has('header_background_color')) {
            $settings['header_background_color'] = 'rgba(13,58,39,0.85)';
        }
        if (!$settings->has('banner_tagline')) {
            $settings['banner_tagline'] = 'Healing OURTH Tableware';
        }
        if (!$settings->has('banner_subtagline')) {
            $settings['banner_subtagline'] = '100% Organic, Natural & Compostable';
        }
        if (!$settings->has('app_text_color')) {
            $settings['app_text_color'] = '#2C1F13';
        }

        return response()->json($settings);
    }

    /**
     * Upsert app settings (admin only).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'app_background_color' => 'nullable|string',
            'header_background_color' => 'nullable|string',
            'app_text_color' => 'nullable|string',
            'banner_tagline' => 'nullable|string',
            'banner_subtagline' => 'nullable|string',
            'banner_image_url' => 'nullable|string',
        ]);

        foreach ($data as $key => $value) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => AppSetting::pluck('value', 'key')
        ]);
    }
}
