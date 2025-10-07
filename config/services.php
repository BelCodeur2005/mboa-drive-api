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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    // Configuration Firebase pour Mboa Drive
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID', 'mboa-drive'),
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),
        'database_url' => env('FIREBASE_DATABASE_URL'),
        'server_key' => env('FIREBASE_SERVER_KEY'),
    ],

    // Configuration SMS
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
        'verify_sid' => env('TWILIO_VERIFY_SID'),
    ],

    'nexmo' => [
        'key' => env('NEXMO_KEY'),
        'secret' => env('NEXMO_SECRET'),
        'sms_from' => env('NEXMO_SMS_FROM', 'MboaDrive'),
    ],

    // Configuration Mobile Money
    'mtn_momo' => [
        'api_url' => env('MTN_MOMO_API_URL', 'https://sandbox.momodeveloper.mtn.com'),
        'primary_key' => env('MTN_MOMO_PRIMARY_KEY'),
        'secondary_key' => env('MTN_MOMO_SECONDARY_KEY'),
        'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
        'environment' => env('MTN_MOMO_ENVIRONMENT', 'sandbox'), // sandbox ou production
    ],

    'orange_money' => [
        'api_url' => env('ORANGE_MONEY_API_URL', 'https://api.orange.com/oauth/v3'),
        'client_id' => env('ORANGE_MONEY_CLIENT_ID'),
        'client_secret' => env('ORANGE_MONEY_CLIENT_SECRET'),
        'merchant_key' => env('ORANGE_MONEY_MERCHANT_KEY'),
    ],

    // Configuration Pusher pour WebSockets
    'pusher' => [
        'app_id' => env('PUSHER_APP_ID'),
        'app_key' => env('PUSHER_APP_KEY'),
        'app_secret' => env('PUSHER_APP_SECRET'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'encrypted' => true,
            'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusherapp.com',
            'port' => env('PUSHER_PORT', 443),
            'scheme' => env('PUSHER_SCHEME', 'https'),
        ],
    ],

    // Configuration Google Maps
    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
        'places_api_key' => env('GOOGLE_PLACES_API_KEY', env('GOOGLE_MAPS_API_KEY')),
    ],

    // Configuration des notifications
    'notifications' => [
        'channels' => [
            'push' => env('NOTIFICATIONS_PUSH_ENABLED', true),
            'email' => env('NOTIFICATIONS_EMAIL_ENABLED', true),
            'sms' => env('NOTIFICATIONS_SMS_ENABLED', true),
        ],
        'rate_limits' => [
            'per_user_per_hour' => env('NOTIFICATION_RATE_LIMIT', 50),
            'sms_per_user_per_day' => env('SMS_RATE_LIMIT', 10),
        ],
    ],

];
