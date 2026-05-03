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
        'play_json_path' => env('GOOGLE_PLAY_JSON_PATH'),
    ],

    'external_token' => env('EXTERNAL_TOKEN'),

    'telegram_debug' => [
        'token' => env('TELEGRAM_DEBUG_TOKEN', ''),
        'chat_id' => env('TELEGRAM_DEBUG_CHAT_ID', ''),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o'),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'telegram_support_link' => env('TELEGRAM_SUPPORT_LINK'),

    'free_package_limit' => env('FREE_PACKAGE_LIMIT', 5),

    'locales' => [
        'en' => 'English',
        'nl' => 'Nederlands',
        'fr' => 'Français',
        'de_DE' => 'Deutsch',
        'id' => 'Bahasa Indonesia',
        'it' => 'Italiano',
        'ja' => '日本語',
        'pt_BR' => 'Português (Brasil)',
        'ru' => 'Русский',
        'es_ES' => 'Español',
        'zh_Hans' => '简体中文',
        'zh_Hant' => '繁體中文',
        'tr' => 'Türkçe',
    ],

    'default_locale' => 'en',

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
        'sandbox' => env('APPLE_SANDBOX', false),
        'app_store_id' => env('APPLE_APP_STORE_ID', '6755874667'),
        'app_store_url' => env('APPLE_APP_STORE_URL', 'https://apps.apple.com/us/app/vital-scan/id6755874667'),
    ],

    'play_store_url' => env('PLAY_STORE_URL', 'https://play.google.com/store/apps/details?id=com.healthyproduct.app'),

    'app_version' => [
        'ios' => env('APP_VERSION_IOS', '1.0.13'),
        'android' => env('APP_VERSION_ANDROID', '1.0.13'),
    ],

];
