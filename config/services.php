<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de_DE facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'free_package_limit' => 10,

    'locales' => ['az' => 'Azərbaycan','en' => 'English', 'ru' => 'Русский', 'tr' => 'Türkçe', 'es_ES' => 'Español','de_DE' => 'Deutsch'],

    'default_locale' => 'az',

    'wallet_pay' => [
        'base_url' => env('WALLET_PAY_BASE_URL','DEFAULT_URL'),
        'api_key' => env('WALLET_PAY_API_KEY', 'DEFAULT_API_KEY'),
        'bot_username' => 'VitalScanBot'
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'bundle_id' => env('APPLE_BUNDLE_ID', 'com.vscan.vitalscan'),
        'issuer_id' => env('APPLE_ISSUER_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key_path' => env('APPLE_PRIVATE_KEY_PATH'),
        'app_store_id' => env('APPLE_APP_STORE_ID', '6755874667'),
        'app_store_url' => env('APPLE_APP_STORE_URL', 'https://apps.apple.com/us/app/vital-scan/id6755874667'),
    ],

    'play_store_url' => env('PLAY_STORE_URL', 'https://play.google.com/store/apps/details?id=com.healthyproduct.app'),

];
