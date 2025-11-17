<?php

namespace App\Services;

use App\Constants\TelegramConstants;
use App\Enums\SubscriptionStatus;
use App\Models\Categories;
use App\Models\CustomerPackages;
use App\Models\Customers;
use App\Models\Packages;
use App\Models\ScanResults;
use App\Models\Subscription;
use App\Services\Traits\TranslationTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Objects\CallbackQuery;

class TelegramService
{
    use TranslationTrait;

    // --- A. İSTİFADƏÇİ VƏ GÖNDƏRİŞ METODLARI ---

    public function syncTelegramUser($from): ?Customers
    {
        if (!$from) {
            return null;
        }

        $telegramId = $from->getId();
        $data = [
            'name' => $from->getFirstName() ?? '',
            'surname' => $from->getLastName() ?? '',
            'telegram_username' => $from->getUsername() ?? '',
            'telegram_language' => $from->get('language_code') ?? '',
        ];

        $customer = Customers::updateOrCreate(
            ['telegram_id' => $telegramId],
            $data
        );

        Log::info(($customer->wasRecentlyCreated ? "Yeni " : "Yenilənmiş ") . "Telegram istifadəçisi: $telegramId");
        return $customer;
    }

    public function getCustomerByFrom($from): ?Customers
    {
        return Customers::where('telegram_id', $from->getId())->first();
    }

    public function sendMessage(int $chatId, string $text, string $parseMode = null, array $replyMarkup = []): void
    {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode) {
            $data['parse_mode'] = $parseMode;
        }

        if (!empty($replyMarkup)) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        Telegram::sendMessage($data);
    }

    // --- B. AI VƏ SKAN MƏNTİQİ ---

    public function getOpenAIResponse(string $imageUrl, string $categoryName, string $languageName)
    {
        $openai = OpenAI::client(env('OPENAI_API_KEY'));

        return $openai->chat()->create([
            'model' => env('OPENAI_VISION_MODEL', 'gpt-4o-mini'),
            'temperature' => 0.0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => <<<EOT
                                You are a product analysis system.

                                Analyze the image of the product label and return a structured JSON response.

                                Rules:
                                1. Detect the **actual product name** and **product category** from the label. Do NOT rely on or copy the category provided by the user. If product name or category cannot be determined, return `null` for them.
                                2. Analyze the ingredients and dynamically calculate a **health score** according to the category specified by the user (e.g., Children, Adults, Diabetics, Allergic people). For example, a product that is healthy in general may be unhealthy for children or allergic individuals.
                                3. Always respond in the **language specified by the user** (including product name, category, ingredients, score, etc.).
                                4. If valid information is found, include `"check": true`. If important data is missing or cannot be interpreted, set `"check": false`.

                                Return the result in this exact JSON format:
                                {
                                  "check": true or false,
                                  "product_name": "Detected product name in the user's language or null",
                                  "category": "Detected product category in the user's language or null",
                                  "ingredients": ["List of all ingredients in the user's language"],
                                  "worst_ingredients": ["List of worst ingredients for health, **based on the user's specified category**, in user's language"],
                                  "best_ingredients": ["List of best ingredients for health, **based on the user's specified category**, in user's language"],
                                  "health_score": "A percentage score **based on the specified category**, considering how suitable the ingredients are for that group",
                                  "detail_text": "Detailed explanation in the user's language, summarizing health evaluation"
                                }

                                Adjust the health_score more strictly:
                                    • If there are more than 3 worst_ingredients, reduce the health_score by at least 20%.
                                    • If there are fewer than 2 best_ingredients, reduce the health_score by 10%.
                                    • If the number of worst_ingredients is greater than the number of best_ingredients, reduce the health_score by 20%.

                                EOT
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Analyze the contents of this product and respond in the specified JSON format.
Write the ingredients (all, worst, best), health score (based on category: **$categoryName**), product name, product category, and detailed explanation in **$languageName**.
Category: **$categoryName**, Language: **$languageName**."
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $imageUrl
                            ]
                        ]
                    ]
                ]
            ],
            'response_format' => ['type' => 'json_object'],
        ]);
    }

    private function saveScanResult(Customers $customer, array $aiResponseData, string $path, int $timeMs, $activePackage, string $key, int $attempts): void
    {
        ScanResults::create([
            'customer_id' => $customer->id,
            'category_id' => $customer->default_category_id,
            'image' => $path,
            'response' => $aiResponseData,
            'category_name_ai' => $aiResponseData['category'] ?? '',
            'product_name_ai' => $aiResponseData['product_name'] ?? '',
            'product_score' => isset($aiResponseData['health_score']) && $aiResponseData['health_score'] !== 'null'
                ? (int) str_replace('%', '', $aiResponseData['health_score'])
                : null,
            'check' => $aiResponseData['check'],
            'response_time' => $timeMs,
        ]);

        if (!$aiResponseData['check']) {
            Cache::put($key, $attempts + 1, now()->addMinutes(5));
            if ($attempts >= 3 && $activePackage) {
                $activePackage->decrement('remaining_scans');
            }
        } elseif ($aiResponseData['check'] && $activePackage) {
            $activePackage->decrement('remaining_scans');
        }
    }

    public function handleProductImage(int $chatId, $message, $from): void
    {
        $startTime = microtime(true);
        $customer = $this->getCustomerByFrom($from);
        $languageCode = $customer->language ?? TelegramConstants::DEFAULT_LANGUAGE;
        $languageName = $this->mapLangNameToCode($languageCode, true);

        $activePackage = $customer->packages()
            ->where('remaining_scans', '>', 0)
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->orderByDesc('id')
            ->first();

        // 1. Limit Yoxlamaları
        if ($customer->scan_results()->count() >= 3 && !$activePackage) {
            $this->sendMessage($chatId, $this->translate('out_of_scan', [], $languageCode));
            $this->showStarPackages($chatId, $languageCode); // Paketləri də göstər
            return;
        }

        $key = 'scan_limit_for_unchecked_' . $from->getId();
        $attempts = Cache::get($key, 0);

        if ($attempts >= 5) {
            $this->sendMessage($chatId, $this->translate('scan_limit_unreached_error', [], $languageCode), 'Markdown');
            return;
        }

        // 2. Şəklin Yüklənməsi
        $photos = $message->getPhoto();
        $array = json_decode(json_encode($photos), true);
        $photo = end($array);
        $fileId = $photo['file_id'] ?? null;

        if (!$fileId) {
            $this->sendMessage($chatId, "⚠️ Foto oxuna bilmədi. Yenidən göndərin.");
            return;
        }

        $file = Telegram::getFile(['file_id' => $fileId]);
        $token = config('telegram.bots.mybot.token');
        $url = "https://api.telegram.org/file/bot{$token}/" . $file->getFilePath();

        $contents = file_get_contents($url);
        $path = 'scan_results/' . time() . '_' . md5($chatId) . '.jpg';
        Storage::disk('public')->put($path, $contents);
        $fullUrl = asset('storage/' . $path);

        $category = Categories::find($customer->default_category_id);
        $categoryName = $category->getTranslation('name', 'en');

        $this->sendMessage($chatId, $this->translate('please_wait', [], $languageCode));

        // 3. AI Analiz
        $aiResponse = $this->getOpenAIResponse($fullUrl, $categoryName, $languageName);
        $aiResponseData = json_decode($aiResponse->choices[0]->message->content, true);

        $timeMs = (int)((microtime(true) - $startTime) * 1000);

        // 4. Nəticəni Yaddaşa Yazmaq
        $this->saveScanResult($customer, $aiResponseData, $path, $timeMs, $activePackage, $key, $attempts);

        // 5. Nəticəni Göndərmək
        if (!$aiResponseData['check']) {
            $this->sendMessage($chatId, $this->translate('scan_limit', [], $languageCode), 'Markdown');
            return;
        }

        $this->sendScanResult($chatId, $aiResponseData, $categoryName, $timeMs, $languageCode);
    }

    private function sendScanResult(int $chatId, array $data, string $categoryName, int $timeMs, string $languageCode): void
    {
        $ingredients = $data['ingredients'] ?? [];
        $best = $data['best_ingredients'] ?? [];
        $worst = $data['worst_ingredients'] ?? [];
        $detailText = $data['detail_text'] ?? '';

        $ingredientsText = !empty($ingredients) ? "🧪 *Ingredients:*\n" . implode(", ", $ingredients) . "\n" : '';
        $bestText = !empty($best) ? "🌿 *Best Ingredients:*\n" . "• " . implode("\n• ", $best) . "\n" : '';
        $worstText = !empty($worst) ? "⚠️ *Worst Ingredients:*\n" . "• " . implode("\n• ", $worst) . "\n" : '';
        $detailText = !empty($detailText) ? "ℹ️ *Details:*\n" . "• " . $detailText . "\n" : '';

        $translateData['product_name'] = $data['product_name'] ?? 'Unknown';
        $translateData['category'] = $categoryName ?? $data['category'];
        $translateData['health_score'] = $data['health_score'] ?? 'N/A';
        $translateData['ingredients'] = $ingredientsText;
        $translateData['best_ingredients'] = $bestText;
        $translateData['worst_ingredients'] = $worstText;
        $translateData['details'] = $detailText;
        $translateData['response_time'] = $timeMs;

        $getWord = $this->translate('scan_result', $translateData);

        $this->sendMessage($chatId, $getWord[$languageCode], 'Markdown');
    }

    // --- C. DİL VƏ KATEQORİYA MƏNTİQİ ---

    public function showLanguageSelection(int $chatId): void
    {
        $languages = collect([
            // ['code' => 'az', 'flag' => '🇦🇿', 'name' => 'Azerbaijani'], // Aktiv deyil
            ['code' => 'en', 'flag' => '🇬🇧', 'name' => 'English'],
            ['code' => 'ru', 'flag' => '🇷🇺', 'name' => 'Russian'],
            ['code' => 'es_ES', 'flag' => '🇪🇸', 'name' => 'Spanish'],
            ['code' => 'de_DE', 'flag' => '🇩🇪', 'name' => 'German'],
            ['code' => 'tr', 'flag' => '🇹🇷', 'name' => 'Turkish'],
        ]);

        Cache::put('languages_list', $languages, now()->addMinutes(30));

        $keyboard = [];
        foreach ($languages->chunk(2) as $chunk) {
            $row = [];
            foreach ($chunk as $lang) {
                $row[] = ['text' => "{$lang['flag']} {$lang['name']}", 'callback_data' => TelegramConstants::CALLBACK_LANGUAGE_PREFIX . $lang['code']];
            }
            $keyboard[] = $row;
        }

        $this->sendMessage($chatId, "🌍 First, please select your language 👇", 'Markdown', ['inline_keyboard' => $keyboard]);
    }

    public function handleLanguageSelection(int $chatId, string $data, $from): void
    {
        $customer = $this->getCustomerByFrom($from);
        $languageCode = explode(TelegramConstants::CALLBACK_LANGUAGE_PREFIX, $data)[1] ?? TelegramConstants::DEFAULT_LANGUAGE;

        $customer->language = $languageCode;
        $customer->save();

        $sendData['language_name'] = $this->mapLangNameToCode($languageCode, true);
        $getWord = $this->translate('choose_category', $sendData);

        $this->sendMessage($chatId, $getWord[$languageCode], 'Markdown');
    }

    public function showCategories(int $chatId, $from): void
    {
        $customer = $this->getCustomerByFrom($from);
        $langCode = $customer->language ?? TelegramConstants::DEFAULT_LANGUAGE;

        $categories = Categories::all()->map(function ($category) use ($langCode) {
            return [
                'id' => $category->id,
                'name' => $category->getTranslation('name', $langCode) ?? 'Unknown',
                'emoji' => $category->emoji ?? '📁',
            ];
        });

        Cache::put('categories_list', $categories, now()->addMinutes(30));

        $keyboard = [];
        foreach ($categories->chunk(2) as $chunk) {
            $row = [];
            foreach ($chunk as $c) {
                $row[] = ['text' => "{$c['emoji']} {$c['name']}", 'callback_data' => TelegramConstants::CALLBACK_CATEGORY_PREFIX . $c['id']];
            }
            $keyboard[] = $row;
        }

        $getWord = $this->translate('back_home');
        $keyboard[] = [['text' => $getWord[$langCode], 'callback_data' => 'choose_language']]; // Back to language

        $getWord = $this->translate('choose_category_2');

        $this->sendMessage($chatId, $getWord[$langCode], 'Markdown', ['inline_keyboard' => $keyboard]);
    }

    public function handleCategorySelection(int $chatId, string $data, $from): void
    {
        $customer = $this->getCustomerByFrom($from);
        $categoryId = explode(TelegramConstants::CALLBACK_CATEGORY_PREFIX, $data)[1] ?? 1;

        $getCategory = Categories::findOrFail($categoryId);

        if ($getCategory) {
            $sendData['category_name'] = $getCategory->emoji . " " . $getCategory->getTranslation('name', $customer->language);
        } else {
            $sendData['category_name'] = '';
        }

        $getWord = $this->translate('chosen_category', $sendData);

        $customer->default_category_id = $getCategory->id ?? 1;
        $customer->save();

        $this->sendMessage($chatId, $getWord[$customer->language ?? TelegramConstants::DEFAULT_LANGUAGE], 'Markdown');
    }

    // --- D. ÖDƏNİŞ VƏ PAKET MƏNTİQİ ---

    public function showStarPackages(int $chatId, string $languageCode): void
    {
        $packages = Packages::all();

        $keyboard = [];
        foreach ($packages as $pkg) {
            $btnText = "{$pkg->telegram_emoji} {$pkg->getTranslation('name',$languageCode)} – {$pkg->scan_count} scans";

            if ($pkg->saving > 0) {
                $btnText .= " (−{$pkg->saving}%)";
            }

            $btnText .= " – {$pkg->telegram_star_price} ⭐";

            $keyboard[] = [['text' => $btnText, 'callback_data' => TelegramConstants::CALLBACK_BUY_PREFIX . $pkg->product_id_for_purchase]];
        }

        $this->sendMessage($chatId, $this->translate('out_of_scan_packages', [], $languageCode)['en'], null, ['inline_keyboard' => $keyboard]);
    }

    public function sendInvoice(int $chatId, Packages $package): void
    {
        Telegram::sendInvoice([
            'chat_id' => $chatId,
            'title' => $package->name,
            'description' => "Unlock {$package->scan_count} additional scans in VitalScan.",
            'payload' => TelegramConstants::PACKAGE_PAYLOAD_PREFIX . $package->id,
            'provider_token' => TelegramConstants::TELEGRAM_STARS_PROVIDER_TOKEN,
            'currency' => TelegramConstants::TELEGRAM_STARS_CURRENCY,
            'prices' => [
                ["label" => "{$package->scan_count} Scans", "amount" => intval($package->telegram_star_price)]
            ],
        ]);
    }

    public function handleSuccessfulPayment(Update $update, $from): void
    {
        $customer = $this->getCustomerByFrom($from);
        $payment = $update['message']['successful_payment'];
        $payload = $payment['invoice_payload'];
        $chatId = $update['message']['chat']['id'];

        $packageId = intval(str_replace(TelegramConstants::PACKAGE_PAYLOAD_PREFIX, '', $payload));
        $package = Packages::find($packageId);

        if (!$package) {
            $this->sendMessage($chatId, "❗ Payment received, but package not found.");
            return;
        }

        DB::transaction(function () use ($customer, $package, $update, $payment) {
            $purchase = Subscription::create([
                'customer_id' => $customer->id,
                'product_id' => $package->id,
                'platform' => 'telegram',
                'purchase_token' => $payment['telegram_payment_charge_id'],
                'start_date' => now(),
                'status' => SubscriptionStatus::ACTIVE->value,
                'payment_details' => json_encode($update),
                'amount' => $payment['total_amount'] ?? 0,
            ]);

            CustomerPackages::create([
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'remaining_scans' => $package->scan_count,
                'subscription_id' => $purchase->id,
                'status' => SubscriptionStatus::ACTIVE->value,
            ]);
        });

        $msg = "🎉 You have successfully purchased *{$package->scan_count} extra scans*!\n"
            . "✨ Package: *{$package->name}*";

        $this->sendMessage($chatId, $msg, 'Markdown');
    }

    // --- E. STATİK VƏ PROFİL MƏNTİQİ ---

    public function sendWelcomeMessage(int $chatId, string $name): void
    {
        $this->sendMessage(
            $chatId,
            "👋 Hello, *{$name}!*\n" .
            "*Welcome to Vital Scan – Product Analysis System!*\n\n" .
            "🌍 Please select your preferred *language*, then choose a *category* to begin the analysis.\n\n" .
            "🔄 You can change your language and category selections at any time.",
            'Markdown'
        );
    }

    public function getProfileData(int $chatId, $from): void
    {
        $getCustomer = $this->getCustomerByFrom($from);

        $msg = "👤 Your Profile

• *Name:* " . $getCustomer->name . " " . $getCustomer->surname . "
• *Username:* @" . $getCustomer->telegram_username . "
• *Credits:* 45 (Not implemented yet)
• *Premium:* No (Not implemented yet)
• *Joined:* " . \Carbon\Carbon::parse($getCustomer->created_at)->format('d/m/Y') . "

Choose an action:";

        $keyboard = [
            [['text' => 'Usage History', 'callback_data' => "usage_history"]],
            [['text' => 'Payment History', 'callback_data' => "payment_history"]],
            [['text' => 'Buy Package', 'callback_data' => "profile_buy_package"]],
            [['text' => 'Support', 'callback_data' => "support"]],
            [['text' => 'Back to Home', 'callback_data' => "choose_language"]],
        ];

        $this->sendMessage($chatId, $msg, 'Markdown', ['inline_keyboard' => $keyboard]);
    }

    public function getStaticPageData(int $chatId, string $type = 'privacy'): void
    {
        $html = '';

        if ($type == 'privacy') {
            $html = '<b>🔒 Privacy Policy — VitalScan AI Bot</b>

We respect your privacy. Below is a short summary of how we handle your data:

<b>📥 Data we collect:</b>
        • Images you send (deleted after analysis)
        • Messages/commands
        • Telegram ID, name, language
        • Telegram Stars payment info (we don\'t receive card data)

<b>🎯 How we use it:</b>
        • To analyze product labels
        • To store language & category settings
        • To manage scan limits & purchases

<b>❌ What we don’t do:</b>
        • No selling or sharing of your data
        • No storing of card details

<b>📩 Contact:</b> support@vitalscan.app
<b>🌐 Website:</b> <a href="https://vitalscan.app">vitalscan.app</a>';
        } elseif ($type == 'terms') {
            $html = '<b>📄 Terms & Conditions — VitalScan AI Bot</b>

By using VitalScan AI Bot, you agree to the terms below:

<b>1️⃣ Service Description</b>
VitalScan analyzes product label images and provides ingredient insights, health scores, and related data for informational purposes only.

<b>2️⃣ User Responsibilities</b>
• You must provide clear and accurate images.
• You agree not to misuse the bot or send harmful/unlawful content.
• The analysis provided is not medical or professional advice.

<b>3️⃣ Payments (Telegram Stars)</b>
• Optional paid packages are available through Telegram Stars.
• All purchases are handled securely by Telegram.
• No refunds are provided unless required by law.

<b>4️⃣ Data Usage</b>
• Images are deleted after analysis.
• We store minimal data (Telegram ID, language, scan limits).
• No financial or card data is ever collected by us.

<b>5️⃣ Limitations</b>
• The bot may not always correctly read or interpret labels.
• We are not responsible for incorrect or incomplete results.

<b>6️⃣ Service Changes</b>
We may update features or modify these terms at any time.

<b>7️⃣ Contact</b>
For questions or support: support@vitalscan.app
Website: <a href="https://vitalscan.app">vitalscan.app</a>
';
        } elseif ($type == 'about_us') {
            $html =
                "<b>🔍 About — VitalScan AI Bot</b>\n\n" .
                "Welcome to <b>VitalScan AI Bot</b> — your quick, in-Telegram assistant for ingredient analysis and health guidance.\n\n" .

                "<b>🎯 What we do</b>\n" .
                "• Analyze product labels with AI and return ingredient lists.\n" .
                "• Show health scores and highlight best / worst ingredients.\n" .
                "• Provide results in multiple languages, directly inside Telegram.\n\n" .

                "<b>🔒 Trust & Privacy</b>\n" .
                "• Images are processed and not stored permanently.\n" .
                "• Payments (if any) are handled by Telegram; we do not receive card details.\n\n" .

                "<b>📬 Contact</b>\n" .
                "Email: <a href=\"mailto:support@vitalscan.app\">support@vitalscan.app</a>\n" .
                "Website: <a href=\"https://vitalscan.app\">vitalscan.app</a>\n";
        }

        $this->sendMessage($chatId, $html, 'HTML');
    }
}
