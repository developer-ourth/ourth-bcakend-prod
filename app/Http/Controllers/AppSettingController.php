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
        if (!$settings->has('website_primary_color')) {
            $settings['website_primary_color'] = '#2B4D0E';
        }
        if (!$settings->has('website_accent_color')) {
            $settings['website_accent_color'] = '#E8A33A';
        }
        if (!$settings->has('website_announcement_bar_text')) {
            $settings['website_announcement_bar_text'] = '🌱 Earn 5 Green Points (₹5 Cashback) per ₹100 spent on all orders!';
        }
        if (!$settings->has('website_announcement_bar_enabled')) {
            $settings['website_announcement_bar_enabled'] = 'true';
        }
        if (!$settings->has('website_home_banner_title')) {
            $settings['website_home_banner_title'] = '100% Compostable Areca Leaf Tableware';
        }
        if (!$settings->has('website_home_banner_subtitle')) {
            $settings['website_home_banner_subtitle'] = 'Directly from nature to your table. Zero plastics, zero chemicals.';
        }

        return response()->json($settings);
    }

    /**
     * Upsert app & website settings (admin only).
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
            'app_banner_slides' => 'nullable|string',

            // Website Settings
            'website_home_banner_url' => 'nullable|string',
            'website_home_banner_title' => 'nullable|string',
            'website_home_banner_subtitle' => 'nullable|string',
            'website_marketplace_banner_url' => 'nullable|string',
            'website_marketplace_tagline' => 'nullable|string',
            'website_campaign_banner_url' => 'nullable|string',
            'website_campaign_tagline' => 'nullable|string',
            'website_announcement_bar_text' => 'nullable|string',
            'website_announcement_bar_enabled' => 'nullable|string',
            'website_primary_color' => 'nullable|string',
            'website_accent_color' => 'nullable|string',
            'website_announcement_bg' => 'nullable|string',

            // Shadowfax Pickup & Return Store Settings
            'pickup_name' => 'nullable|string',
            'pickup_phone' => 'nullable|string',
            'pickup_address_1' => 'nullable|string',
            'pickup_address_2' => 'nullable|string',
            'pickup_city' => 'nullable|string',
            'pickup_state' => 'nullable|string',
            'pickup_pincode' => 'nullable|string',

            // Meta & WhatsApp & Google Places API Credentials
            'whatsapp_phone_number_id' => 'nullable|string',
            'whatsapp_access_token' => 'nullable|string',
            'meta_pixel_id' => 'nullable|string',
            'meta_capi_token' => 'nullable|string',
            'google_places_api_key' => 'nullable|string',
        ]);

        foreach ($data as $key => $value) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value ?? '']
            );
        }

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => AppSetting::pluck('value', 'key')
        ]);
    }
}
