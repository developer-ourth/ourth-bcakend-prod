<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_KEY_ID'),
        'secret' => env('RAZORPAY_KEY_SECRET'),
        'base_url' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'shadowfax' => [
        'api_token' => env('SHADOWFAX_API_TOKEN'),
        'base_url' => env('SHADOWFAX_BASE_URL', 'https://api.shadowfax.in'),
    ],

    // Meta webhooks (Lead Ads + WhatsApp Cloud API). Pixel/CAPI tokens stay in
    // Website Settings (AppSetting) so their existing precedence is unchanged.
    'meta' => [
        'app_secret' => env('META_APP_SECRET'),
        'verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'leads_access_token' => env('META_LEADS_ACCESS_TOKEN'),
        // Approved WhatsApp template sent to new Lead Ad leads; its body takes {{1}} = name.
        'lead_welcome_template' => env('META_LEAD_WELCOME_TEMPLATE'),
        'template_language' => env('META_TEMPLATE_LANGUAGE', 'en'),
    ],

    // Zoho Inventory (India data centre). Zoho is the source of truth for stock;
    // paid website orders become sales orders + invoices + payments in Zoho.
    'zoho' => [
        'client_id' => env('ZOHO_CLIENT_ID'),
        'client_secret' => env('ZOHO_CLIENT_SECRET'),
        'refresh_token' => env('ZOHO_REFRESH_TOKEN'),
        'organization_id' => env('ZOHO_ORGANIZATION_ID'),
        'accounts_url' => rtrim((string) env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.in'), '/'),
        'api_url' => rtrim((string) env('ZOHO_API_URL', 'https://www.zohoapis.in/inventory/v1'), '/'),
        'stock_sync_minutes' => (int) env('ZOHO_STOCK_SYNC_MINUTES', 10),
        // Empty = use the first of available_for_sale_stock, actual_available_stock, available_stock, stock_on_hand
        'stock_field' => env('ZOHO_STOCK_FIELD'),
        // Website prices include GST, so Zoho must treat line rates as tax-inclusive
        'prices_include_tax' => (bool) env('ZOHO_PRICES_INCLUDE_TAX', true),
        'payment_mode' => env('ZOHO_PAYMENT_MODE', 'others'),
        'payment_account_id' => env('ZOHO_PAYMENT_ACCOUNT_ID'),
        // Only orders placed on/after this date (Y-m-d) are sent to Zoho; earlier ones were entered manually
        'orders_from' => env('ZOHO_ORDERS_FROM'),
    ],

    // Self-hosted Krayin CRM (see ourth-social-media/README.md).
    'krayin' => [
        'url' => rtrim((string) env('KRAYIN_URL', ''), '/'),
        'api_email' => env('KRAYIN_API_EMAIL'),
        'api_password' => env('KRAYIN_API_PASSWORD'),
    ],

    // Self-hosted Chatwoot inbox. Laravel receives Meta's WhatsApp webhook first
    // (to record ad attribution) and forwards the raw payload here.
    'chatwoot' => [
        'url' => rtrim((string) env('CHATWOOT_URL', ''), '/'),
        'whatsapp_webhook_url' => env('CHATWOOT_WHATSAPP_WEBHOOK_URL'),
    ],

];
