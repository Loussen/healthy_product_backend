<?php

namespace App\Constants;

class TelegramConstants
{
    // Telegram Stars valyuta kodu
    public const TELEGRAM_STARS_CURRENCY = 'XTR';

    // Stars ödənişində provider tokeni boş saxlanmalıdır
    public const TELEGRAM_STARS_PROVIDER_TOKEN = '';

    // Məhsul ID-nin prefiksi
    public const PACKAGE_PAYLOAD_PREFIX = 'pkg_';

    // Callback datası üçün prefikslər
    public const CALLBACK_BUY_PREFIX = 'buy_';
    public const CALLBACK_LANGUAGE_PREFIX = 'lang_';
    public const CALLBACK_CATEGORY_PREFIX = 'category_';

    // Standart dil
    public const DEFAULT_LANGUAGE = 'en';

    // Standart əmrlər
    public const COMMAND_START = '/start';
    public const COMMAND_LANGUAGE = '/language';
    public const COMMAND_CATEGORY = '/category';
    public const COMMAND_PROFILE = '/profile';
    public const COMMAND_PACKAGES = '/packages';
    public const COMMAND_PRIVACY = '/privacy';
    public const COMMAND_TERMS = '/terms';
    public const COMMAND_ABOUT_US = '/about_us';
    public const COMMAND_SUPPORT_US = '/support';

    public const COMMAND_USAGE_HISTORY = '/usage';
    public const COMMAND_PAYMENT_HISTORY = '/payment_history';

    public const ATTEMPT_COUNT = 5;

    public const FREE_SCAN_LIMIT = 3;

    // Mətnlərdə istifadə olunan dillərin adları/kodları xəritəsi
    public const LANGUAGE_MAP = [
        'Azerbaijani' => 'az',
        'English' => 'en',
        'Russian' => 'ru',
        'Turkish' => 'tr',
        'Spanish' => 'es_ES',
        'German' => 'de_DE',
    ];
}
