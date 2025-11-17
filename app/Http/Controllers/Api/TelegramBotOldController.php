<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Models\Categories;
use App\Models\CustomerPackages;
use App\Models\Customers;
use App\Models\Packages;
use App\Models\ScanResults;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\CallbackQuery;

class TelegramBotOldController extends BaseController
{
    public function handleWebhook(Request $request)
    {
        $update = Telegram::commandsHandler(true);

        Log::info($update);

        $callback = $update->callback_query ?? '';

        // CHECKOUT APPROVE
        if (!empty($update['pre_checkout_query'])) {

            Telegram::answerPreCheckoutQuery([
                'pre_checkout_query_id' => $update['pre_checkout_query']['id'],
                'ok' => true,
            ]);

            return;
        }

        $message = $update->getMessage();

        if (!$message) {
            return response('No message', 200);
        }

        // Hata veren kodu bu güvenli yöntemle değiştirin:

        // 1. Chat nesnesini almayı dene
        $chat = $message->get('chat');

        // 2. Chat nesnesi alınamazsa ve mesaj bir callback sorgusu ise, oradan mesajı çekmeyi dene
        if (!$chat && $message instanceof CallbackQuery && $message->getMessage()) {
            $chat = $message->getMessage()->get('chat');
        }

        if (!$chat) {
            // Hala chat nesnesi yoksa, loglayıp bırak
            Log::error('Chat verisi alinamadi. Gelen nesne tipi: ' . get_class($message));
            return response('Could not retrieve chat data', 200);
        }

        // $from nesnesini alırken de güvenli erişimi kullanın
        $from = $message->get('from');

        Log::info("FROM: ".$from);
        Log::info("Callback: ".$callback);

        if ($from->is_bot) {
            $from = $callback->from;
        }

        // ChatId'yi doğrudan al (get('id') veya get('id')'yi kullan)
        $chatId = $chat->get('id') ?? $chat['id'];

        // User create or update
        $this->syncTelegramUser($from);

        if (!empty($callback)) {
            $data = $callback['data'];

            if(str_starts_with($data, 'buy_')) {
                $this->callbackQueryForStarPackages($data, $chatId);

                return;
            }
        }

        if (!empty($update['message']['successful_payment'])) {
            $this->successPayment($update, $from);

            return;
        }

        $text = trim($message->getText() ?? '');

        // 🟢 1️⃣ /start → dil seçimi
        if ($text === '/start') {
            $this->sendWelcomeMessage($chatId, $from->getFirstName());

            $this->showLanguageSelection($chatId);

            return;
        }

        // 🟡 Dil seçimi menyusu
        if ((!empty($callback) && $data == "choose_language") || $text === '/language') {
            $this->showLanguageSelection($chatId);

            return;
        }

        // 🟠 Dil seçilib
        if (!empty($callback)) {
            $data = $callback['data'];

            if(str_starts_with($data, 'lang_')) {
                $this->handleLanguageSelection($chatId, $data, $from);

                $this->showCategories($chatId,$from);

                return;
            }

        }

        $getCustomer = Customers::where('telegram_id',$from->getId())->first();

        // 🟣 Kateqoriya menyusu
        if ((!empty($callback) && $data == "choose_category") || $text === '/category') {
            if(!$getCustomer->language) {
                $this->showLanguageSelection($chatId);

                return;
            }
            $this->showCategories($chatId,$from);

            return;
        }

        // 🔵 Kateqoriya seçilib
        if (!empty($callback)) {
            $data = $callback['data'];

            if(str_starts_with($data, 'category_')) {
                $this->handleCategorySelection($chatId, $data, $from);

                return;
            }
        }

        // 🟤 Şəkil göndərilibsə
        if ($message->has('photo')) {
            if(!$getCustomer->language) {
                $this->showLanguageSelection($chatId);

                return;
            }
            $category = $getCustomer->default_category_id;
            if(!$category) {
                $this->showCategories($chatId,$from);

                return;
            }
            $this->handleProductImage($chatId, $message, $from);

            return;
        }

        $backHomeTranslations = $this->translate('back_home');
        if (in_array($text, $backHomeTranslations, true)) {
            $this->showLanguageSelection($chatId);
        }

        if($text == '/profile') {
            $this->getProfileData($chatId,$from);

            return;
        }

        if($text == '/privacy') {
            $this->getStaticPageData($chatId,'privacy');

            return;
        }

        if($text == '/terms') {
            $this->getStaticPageData($chatId,'terms');

            return;
        }

        if($text == '/about_us') {
            $this->getStaticPageData($chatId,'about_us');

            return;
        }

        $languageCode = $getCustomer->language ?? 'en';

        if($text == '/packages') {
            $this->showStarPackages($chatId,$languageCode);

            return;
        }

        $getWord = $this->translate('unexpected');

        $keyboard = [
            [
                ['text' => 'Choose a language', 'callback_data' => "choose_language"]
            ],
            [
                ['text' => 'Choose a category', 'callback_data' => "choose_category"]
            ],
        ];

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $getWord[$languageCode],
            'resize_keyboard' => true,
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard
            ])
        ]);

        return response()->json(['ok' => true]);
    }

    // ✅ 1️⃣ Xoş gəldin mesajı
    private function sendWelcomeMessage($chatId, $name): void
    {
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' =>
                "👋 Hello, *{$name}!*\n" .
                "*Welcome to Vital Scan – Product Analysis System!*\n\n" .
                "🌍 Please select your preferred *language*, then choose a *category* to begin the analysis.\n\n" .
                "🔄 You can change your language and category selections at any time.",
            'parse_mode' => 'Markdown',
        ]);
    }

    // ✅ 2️⃣ Dillərin siyahısı
    private function showLanguageSelection($chatId): void
    {
        $languages = collect([
            // ['code' => 'az', 'flag' => '🇦🇿', 'name' => 'Azerbaijani'],
            ['code' => 'en', 'flag' => '🇬🇧', 'name' => 'English'],
            ['code' => 'ru', 'flag' => '🇷🇺', 'name' => 'Russian'],
            ['code' => 'es_ES', 'flag' => '🇪🇸', 'name' => 'Spanish'],
            ['code' => 'de_DE', 'flag' => '🇩🇪', 'name' => 'German'],
            ['code' => 'tr', 'flag' => '🇹🇷', 'name' => 'Turkish'],
        ]);

        Cache::put('languages_list', $languages, now()->addMinutes(30));

        $keyboard = [];

        // 2-li qruplarla düzülüş
        foreach ($languages->chunk(2) as $chunk) {
            $row = [];

            foreach ($chunk as $lang) {
                $row[] = [
                    'text' => "{$lang['flag']} {$lang['name']}",
                    'callback_data' => "lang_{$lang['code']}"
                ];
            }

            $keyboard[] = $row;
        }

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "🌍 First, please select your language 👇",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard
            ])
        ]);
    }


    private function isLanguageSelected($text): bool
    {
        $languages = Cache::get('languages_list', collect());
        return $languages->contains(fn($lang) => str_contains($text, $lang['name']));
    }

    private function handleLanguageSelection($chatId, $data, $from): void
    {
        $getCustomer = Customers::where('telegram_id',$from->getId())->first();

        $language = explode("lang_",$data);
        $languageCode = $language[1];
        $getWord = $this->translate('category');

        $getCustomer->language = $languageCode;
        $getCustomer->save();

        $sendData['language_name'] = $this->mapLangNameToCode($language[1], true);
        $getWord = $this->translate('choose_category',$sendData);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $getWord[$languageCode],
            'parse_mode' => 'Markdown',
        ]);
    }

    // ✅ 3️⃣ Kateqoriyalar
    private function showCategories($chatId, $from): void
    {
        $getCustomer = Customers::where('telegram_id', $from->getId())->first();
        $langCode = $getCustomer->language ?? 'en';

        // Kateqoriyalar
        $categories = Categories::all()->map(function ($category) use ($langCode) {
            return [
                'id' => $category->id,
                'name' => $category->getTranslation('name', $langCode) ?? 'Unknown',
                'emoji' => $category->emoji ?? '📁',
            ];
        });

        Cache::put('categories_list', $categories, now()->addMinutes(30));

        // Inline Keyboard düymələri
        $keyboard = [];

        foreach ($categories->chunk(2) as $chunk) {
            $row = [];
            foreach ($chunk as $c) {
                $row[] = [
                    'text' => "{$c['emoji']} {$c['name']}",
                    'callback_data' => 'category_' . $c['id']
                ];
            }
            $keyboard[] = $row;
        }

        // Back düyməsi
        $getWord = $this->translate('back_home');
        $keyboard[] = [
            ['text' => $getWord[$langCode], 'callback_data' => 'back_home']
        ];

        // Başlıq
        $getWord = $this->translate('choose_category_2');

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $getWord[$langCode],
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard
            ]),
        ]);
    }


    private function isCategorySelected($text): bool
    {
        $categories = Cache::get('categories_list', collect());
        return $categories->contains(fn($c) => str_contains($text, $c['name']));
    }

    private function handleCategorySelection($chatId, $data, $from): void
    {
        $getCustomer = Customers::where('telegram_id',$from->getId())->first();

        Log::info($getCustomer);

        $category = explode('category_',$data);

        $getCategory = Categories::findOrFail($category[1]);

        if ($getCategory) {
            $sendData['category_name'] = $getCategory->emoji . " " . $getCategory->getTranslation('name', $getCustomer->language);
        } else {
            $sendData['category_name'] = '';
        }

        $getWord = $this->translate('chosen_category',$sendData);

        $getCustomer->default_category_id = $getCategory->id ?? 1;
        $getCustomer->save();

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $getWord[$getCustomer->language ?? 'en'],
            'parse_mode' => 'Markdown',
        ]);
    }

    // ✅ 4️⃣ Foto analiz
    private function handleProductImage($chatId, $message, $from)
    {
        $getCustomer = Customers::where('telegram_id',$from->getId())->first();

        $languageCode = $getCustomer->language ?? 'en';
        $language = $this->mapLangNameToCode($languageCode,true);

        $allScans = $getCustomer->scan_results()
            ->count();

        $activePackage = $getCustomer->packages()
            ->where('remaining_scans', '>', 0)
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->orderByDesc('id')
            ->first();

        if($allScans >= 3 && !$activePackage) {
//            $getWord = $this->translate('out_of_scan');
//            Telegram::sendMessage([
//                'chat_id' => $chatId,
//                'text' => $getWord[$languageCode],
//                'parse_mode' => 'Markdown'
//            ]);

            $this->showStarPackages($chatId,$languageCode);

            return;
        }

        $key = 'scan_limit_for_unchecked_' . $from->getId();
        $attempts = Cache::get($key, 0);

        Log::info("Attempts: ".$attempts);

        if ($attempts >= 5) {
            Log::info('Scan limit for unchecked: '.$from->getId());
            $getWord = $this->translate('scan_limit_unreached_error');
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $getWord[$languageCode],
                'parse_mode' => 'Markdown'
            ]);
            return;
        }

        $photos = $message->getPhoto();
        $array = json_decode(json_encode($photos), true);
        $photo = end($array);
        $fileId = $photo['file_id'] ?? null;

        if (!$fileId) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => "⚠️ Foto oxuna bilmədi. Yenidən göndərin."]);
            return;
        }

        $file = Telegram::getFile(['file_id' => $fileId]);
        $token = config('telegram.bots.mybot.token');
        $url = "https://api.telegram.org/file/bot{$token}/" . $file->getFilePath();

        $contents = file_get_contents($url);
        $path = 'scan_results/' . time() . '_' . md5($chatId) . '.jpg';
        Storage::disk('public')->put($path, $contents);
        $fullUrl = asset('storage/' . $path);

        $category = Categories::find($getCustomer->default_category_id);
        $categoryName = $category->getTranslation('name', 'en');

        $getWord = $this->translate('please_wait');

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $getWord[$languageCode],
        ]);

        $openai = OpenAI::client(env('OPENAI_API_KEY'));
        $startTime = microtime(true);

        $aiResponse = $openai->chat()->create([
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
Write the ingredients (all, worst, best), health score (based on category: **$categoryName**), product name, product category, and detailed explanation in **$language**.
Category: **$categoryName**, Language: **$language**."
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $fullUrl
                            ]
                        ]
                    ]
                ]
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        $data = json_decode($aiResponse->choices[0]->message->content, true);
        $timeMs = (int)((microtime(true) - $startTime) * 1000);

        $aiResponseData = json_decode($aiResponse->choices[0]->message->content, true);

        ScanResults::create([
            'customer_id' => $getCustomer->id,
            'category_id' => $getCustomer->default_category_id,
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

        if(!$aiResponseData['check']) {
            Cache::put($key, $attempts + 1, now()->addMinutes(5));

            if($attempts >= 3 && $activePackage) {
                $activePackage->decrement('remaining_scans');
            }

            $getWord = $this->translate('scan_limit');

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $getWord[$languageCode],
                'parse_mode' => 'Markdown'
            ]);

            return;
        }

        if($aiResponseData['check'] && $activePackage)
        {
            $activePackage->decrement('remaining_scans');
        }

        $ingredients = $data['ingredients'] ?? [];
        $best = $data['best_ingredients'] ?? [];
        $worst = $data['worst_ingredients'] ?? [];
        $detailText = $data['detail_text'] ?? [];

        // Liste biçimine çevir
        $ingredientsText = !empty($ingredients) ? "🧪 *Ingredients:*\n" . implode(", ", $ingredients) . "\n" : '';
        $bestText = !empty($best) ? "🌿 *Best Ingredients:*\n" . "• " . implode("\n• ", $best) . "\n" : '';
        $worstText = !empty($worst) ? "⚠️ *Worst Ingredients:*\n" . "• " . implode("\n• ", $worst) . "\n" : '';
        $detailText = !empty($detailText) ? "ℹ️ *Details:*\n" . "• " . $detailText . "\n" : '';

//        $text =
//            "✅ *Product scanned successfully!*\n\n" .
//            "🧾 *Product:* " . ($data['product_name'] ?? 'Unknown') . "\n" .
//            "📦 *Category:* " . ($categoryName ?? $data['category'] ) . "\n" .
//            "💯 *Health Score:* " . ($data['health_score'] ?? 'N/A') . "\n" .
//            $ingredientsText .
//            $bestText .
//            $worstText.
//            $detailText.
//            "🕒 *Response time:* {$timeMs} ms\n\n";

        $translateData['product_name'] = $data['product_name'] ?? 'Unknown';
        $translateData['category'] = $categoryName ?? $data['category'];
        $translateData['health_score'] = $data['health_score'] ?? 'N/A';
        $translateData['ingredients'] = $ingredientsText;
        $translateData['best_ingredients'] = $bestText;
        $translateData['worst_ingredients'] = $worstText;
        $translateData['details'] = $detailText;
        $translateData['response_time'] = $timeMs;
        $getWord = $this->translate('scan_result',$translateData);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $getWord[$languageCode],
            'parse_mode' => 'Markdown',
        ]);
    }

    private function mapLangNameToCode($languageName, $reverse = false): string
    {
        $map = [
            'Azerbaijani' => 'az',
            'English' => 'en',
            'Russian' => 'ru',
            'Turkish' => 'tr',
            'Spanish' => 'es_ES',
            'German' => 'de_DE',
        ];

        if($reverse) {
            $map = array_flip($map);
        }

        return $map[$languageName] ?? 'en';
    }

    private function translate($type, array $data = [])
    {
        if($type == 'category') {
            $messages = [
                'az' => '📋 Kateqoriyalar',
                'en' => '📋 Categories',
                'ru' => '📋 Категории',
                'tr' => '📋 Kategoriler',
                'es_ES' => '📋 Categorías',
                'de_DE' => '📋 Kategorien',
            ];
        } elseif($type == 'choose_category') {
            $messages = [
                'az' => "✅ Seçilmiş dil: *{$data['language_name']}*\n\nİndi kateqoriyanı seç 👇\n\nℹ️ Qeyd: Seçəcəyiniz kateqoriya məhsulun kateqoriyası deyil, sizə aid olan kateqoriyadır. Məsələn: *Vegetarian*",
                'en' => "✅ Selected language: *{$data['language_name']}*\n\nNow choose a category 👇\n\nℹ️ Note: The category you choose is *about you*, not the product. For example: *Vegetarian*",
                'ru' => "✅ Выбранный язык: *{$data['language_name']}*\n\nТеперь выберите категорию 👇\n\nℹ️ Примечание: Категория, которую вы выбираете, относится *к вам*, а не к продукту. Например: *Вегетарианец*",
                'tr' => "✅ Seçilen dil: *{$data['language_name']}*\n\nŞimdi bir kategori seç 👇\n\nℹ️ Not: Seçeceğiniz kategori ürünle ilgili değil, *sizinle* ilgilidir. Örneğin: *Vejetaryen*",
                'es_ES' => "✅ Idioma seleccionado: *{$data['language_name']}*\n\nAhora elige una categoría 👇\n\nℹ️ Nota: La categoría que elijas está *relacionada contigo*, no con el producto. Por ejemplo: *Vegetariano*",
                'de_DE' => "✅ Ausgewählte Sprache: *{$data['language_name']}*\n\nWähle jetzt eine Kategorie 👇\n\nℹ️ Hinweis: Die Kategorie, die du auswählst, bezieht sich *auf dich*, nicht auf das Produkt. Zum Beispiel: *Vegetarier*",
            ];
        } elseif($type == 'choose_category_2') {
            $messages = [
                'az' => '🎯 Kateqoriyanı seç 👇',
                'en' => '🎯 Select a category 👇',
                'ru' => '🎯 Выберите категорию 👇',
                'tr' => '🎯 Kategori seç 👇',
                'es_ES' => '🎯 Selecciona una categoría 👇',
                'de_DE' => '🎯 Wähle eine Kategorie 👇',
            ];
        } elseif($type == 'chosen_category') {
            $messages = [
                'az' => "✅ Seçdiyin kateqoriya: *{$data['category_name']}*\n\n📸 İndi məhsulun *tərkibi hissəsinin* şəklini göndər, analiz edək.",
                'en' => "✅ Selected category: *{$data['category_name']}*\n\n📸 Now send a photo of the *ingredients section* of the product for analysis.",
                'ru' => "✅ Выбранная категория: *{$data['category_name']}*\n\n📸 Теперь отправь фото *раздела с ингредиентами* продукта для анализа.",
                'tr' => "✅ Seçtiğin kategori: *{$data['category_name']}*\n\n📸 Şimdi ürünün *içindekiler kısmının* fotoğrafını gönder, analiz edelim.",
                'es_ES' => "✅ Categoría seleccionada: *{$data['category_name']}*\n\n📸 Ahora envía una foto de la *sección de ingredientes* del producto para analizarla.",
                'de_DE' => "✅ Ausgewählte Kategorie: *{$data['category_name']}*\n\n📸 Sende jetzt ein Foto des *Zutatenbereichs* des Produkts zur Analyse.",
            ];
        } elseif($type == 'please_wait') {
            $messages = [
                'az' => "🔍 Məhsul seçdiyiniz *dil* və *kateqoriya* üzrə analiz olunur...\n\nZəhmət olmasa gözləyin ⏳",
                'en' => "🔍 The product is being analyzed according to your selected *language* and *category*...\n\nPlease wait ⏳",
                'ru' => "🔍 Продукт анализируется согласно выбранным *языку* и *категории*...\n\nПожалуйста, подождите ⏳",
                'tr' => "🔍 Ürün seçtiğiniz *dil* ve *kategoriye* göre analiz ediliyor...\n\nLütfen bekleyin ⏳",
                'es_ES' => "🔍 El producto se está analizando según el *idioma* y la *categoría* seleccionados...\n\nPor favor, espere ⏳",
                'de_DE' => "🔍 Das Produkt wird basierend auf der ausgewählten *Sprache* und *Kategorie* analysiert...\n\nBitte warten Sie ⏳",
            ];
        } elseif($type == 'back_home') {
            $messages = [
                'az' => "🏠 Ana menyuya qayıt",
                'en' => "🏠 Back to main menu",
                'ru' => "🏠 Вернуться в главное меню",
                'tr' => "🏠 Ana menüye dön",
                'es_ES' => "🏠 Volver al menú principal",
                'de_DE' => "🏠 Zur Hauptmenü zurückkehren",
            ];
        } elseif($type == 'unexpected') {
            $messages = [
                'az' => "🤔 Zəhmət olmasa aşağıdakı seçimlərdən birini edin:\n\n" .
                    "🌍 Dil seçin və ya 🎯 Kateqoriya seçin.\n📸 Məhsulun etiket şəklini göndərərək analizə başlayın.",

                'en' => "🤔 Please choose one of the following options:\n\n" .
                    "🌍 Select a language or 🎯 Choose a category.\n📸 Then send a picture of the product label to start the analysis.",

                'ru' => "🤔 Пожалуйста, выберите один из вариантов:\n\n" .
                    "🌍 Выберите язык или 🎯 категорию.\n📸 Затем отправьте фото этикетки продукта для анализа.",

                'tr' => "🤔 Lütfen aşağıdakilerden birini seçin:\n\n" .
                    "🌍 Dil seçin veya 🎯 Kategori seçin.\n📸 Ardından ürün etiketinin fotoğrafını gönderin.",

                'es_ES' => "🤔 Por favor elige una de las siguientes opciones:\n\n" .
                    "🌍 Selecciona un idioma o 🎯 una categoría.\n📸 Luego envía una foto de la etiqueta del producto.",

                'de_DE' => "🤔 Bitte wähle eine der folgenden Optionen:\n\n" .
                    "🌍 Sprache wählen oder 🎯 Kategorie auswählen.\n📸 Sende anschließend ein Foto des Produktetiketts.",
            ];
        } elseif($type == 'scan_limit') {
            $messages = [
                'az' => "🔔 Xəbərdarlıq!\n\nZəhmət olmasa məhsulun tərkib hissələrinin düzgün oxunduğuna əmin olun. Bir neçə uğursuz cəhddən sonra skan etmə prosesi müvəqqəti olaraq dayandırıla bilər.",
                'en' => "🔔 Warning!\n\nPlease make sure the product ingredients are read correctly. After several failed attempts, the scanning process may be temporarily suspended.",
                'ru' => "🔔 Предупреждение!\n\nПожалуйста, убедитесь, что состав продукта считывается правильно. После нескольких неудачных попыток процесс сканирования может быть временно приостановлен.",
                'tr' => "🔔 Uyarı!\n\nLütfen ürünün içerik bilgilerinin doğru okunduğundan emin olun. Birkaç başarısız denemeden sonra tarama işlemi geçici olarak durdurulabilir.",
                'es_ES' => "🔔 ¡Advertencia!\n\nAsegúrate de que los ingredientes del producto se lean correctamente. Tras varios intentos fallidos, el proceso de escaneo puede suspenderse temporalmente.",
                'de_DE' => "🔔 Warnung!\n\nBitte stellen Sie sicher, dass die Produktzutaten korrekt gelesen werden. Nach mehreren fehlgeschlagenen Versuchen kann der Scanvorgang vorübergehend ausgesetzt werden."
            ];
        } elseif($type == 'out_of_scan') {
            $messages = [
                'az' => "⛔ Skan limiti aşılmışdır",
                'en' => "⛔ Out of scan limit",
                'ru' => "⛔ Лимит сканирования превышен",
                'tr' => "⛔ Tarama limiti aşılmıştır",
                'es_ES' => "⛔ Límite de escaneo excedido",
                'de_DE' => "⛔ Scanlimit überschritten"
            ];
        } elseif ($type == 'scan_limit_unreached_error') {
            $messages = [
                'az' => "⚠️ Skan limiti çatdı!\n\nTanınmayan və ya qeyri-aydın şəkilə görə müvəqqəti skan limitinə çatdınız. Zəhmət olmasa bir neçə dəqiqə sonra yenidən cəhd edin və məhsulun tərkib hissələrinin şəklinin aydın və oxunaqlı olmasına diqqət edin.",

                'en' => "⚠️ Scan limit reached!\n\nYou've temporarily reached your scan limit due to an unrecognized or unclear image. Please try again in a few moments and ensure the product ingredient image is clear and readable.",

                'ru' => "⚠️ Достигнут лимит сканирования!\n\nВы временно достигли лимита из-за нераспознанного или нечёткого изображения. Пожалуйста, повторите попытку через несколько минут и убедитесь, что фото состава продукта чёткое и хорошо читается.",

                'tr' => "⚠️ Tarama limiti doldu!\n\nTanınmayan veya bulanık bir görsel nedeniyle geçici olarak tarama limitine ulaştınız. Lütfen birkaç dakika sonra tekrar deneyin ve ürün içeriği görselinin net ve okunabilir olduğundan emin olun.",

                'es_ES' => "⚠️ ¡Límite de escaneo alcanzado!\n\nHas alcanzado temporalmente tu límite de escaneo debido a una imagen no reconocida o borrosa. Por favor, inténtalo de nuevo en unos minutos y asegúrate de que la imagen de los ingredientes del producto sea clara y legible.",

                'de_DE' => "⚠️ Scanlimit erreicht!\n\nSie haben aufgrund eines nicht erkannten oder unscharfen Bildes vorübergehend Ihr Scanlimit erreicht. Bitte versuchen Sie es in ein paar Minuten erneut und stellen Sie sicher, dass das Foto der Produktzutaten klar und gut lesbar ist."
            ];
        } elseif($type === 'out_of_scan_packages') {
            $messages = [
                'az' => "⭐ *Davam etmək üçün paket seçin*\nAşağıdakı paketlərdən birini seçərək analiz limitinizi artıra bilərsiniz.",
                'en' => "⭐ *Choose a package to continue*\nSelect a package below to increase your scan limit.",
                'ru' => "⭐ *Выберите пакет, чтобы продолжить*\nВыберите один из пакетов ниже, чтобы увеличить лимит сканирования.",
                'tr' => "⭐ *Devam etmek için bir paket seçin*\nAşağıdaki paketlerden birini seçerek tarama limitinizi artırabilirsiniz.",
                'es_ES' => "⭐ *Elige un paquete para continuar*\nSelecciona un paquete para aumentar tu límite de escaneos.",
                'de_DE' => "⭐ *Wähle ein Paket, um fortzufahren*\nWähle unten ein Paket, um dein Scanlimit zu erhöhen.",
            ];
        } elseif($type == 'scan_result') {
            $messages = [
                'az' =>
                    "✅ *Məhsul uğurla analiz edildi!*\n
🧾 *Məhsul:* {$data['product_name']}
📦 *Kateqoriya:* {$data['category']}
💯 *Sağlamlıq balı:* {$data['health_score']}

{$data['ingredients']}
{$data['best_ingredients']}
{$data['worst_ingredients']}
{$data['details']}

🕒 *Cavab vaxtı:* {$data['response_time']} ms\n",

                'en' =>
"✅ *Product scanned successfully!*\n
🧾 *Product:* {$data['product_name']}
📦 *Category:* {$data['category']}
💯 *Health Score:* {$data['health_score']}

{$data['ingredients']}
{$data['best_ingredients']}
{$data['worst_ingredients']}
{$data['details']}

🕒 *Response time:* {$data['response_time']} ms\n",

                'ru' =>
"✅ *Продукт успешно проанализирован!*\n
🧾 *Продукт:* {$data['product_name']}
📦 *Категория:* {$data['category']}
💯 *Оценка здоровья:* {$data['health_score']}

{$data['ingredients']}
{$data['best_ingredients']}
{$data['worst_ingredients']}
{$data['details']}

🕒 *Время ответа:* {$data['response_time']} мс\n",

                'tr' =>
"✅ *Ürün başarıyla analiz edildi!*\n
🧾 *Ürün:* {$data['product_name']}
📦 *Kategori:* {$data['category']}
💯 *Sağlık Skoru:* {$data['health_score']}

{$data['ingredients']}
{$data['best_ingredients']}
{$data['worst_ingredients']}
{$data['details']}

🕒 *Yanıt süresi:* {$data['response_time']} ms\n",

                'es_ES' =>
"✅ *¡Producto analizado con éxito!*\n
🧾 *Producto:* {$data['product_name']}
📦 *Categoría:* {$data['category']}
💯 *Puntuación de salud:* {$data['health_score']}

{$data['ingredients']}
{$data['best_ingredients']}
{$data['worst_ingredients']}
{$data['details']}

🕒 *Tiempo de respuesta:* {$data['response_time']} ms\n",

                'de_DE' =>
"✅ *Produkt erfolgreich analysiert!*\n
🧾 *Produkt:* {$data['product_name']}
📦 *Kategorie:* {$data['category']}
💯 *Gesundheitspunktzahl:* {$data['health_score']}

{$data['ingredients']}
{$data['best_ingredients']}
{$data['worst_ingredients']}
{$data['details']}

🕒 *Antwortzeit:* {$data['response_time']} ms\n",
            ];
        }

        return $messages;
    }

    private function syncTelegramUser($from)
    {
        if (!$from) {
            return;
        }

        $telegramId = $from->getId();
        $firstName = $from->getFirstName() ?? '';
        $lastName = $from->getLastName() ?? '';
        $username = $from->getUsername() ?? '';
        $languageCode = $from->get('language_code') ?? '';

        // DB-də axtar
        $customer = Customers::where('telegram_id', $telegramId)->first();

        if (!$customer) {
            // ➕ YENİ İSTİFADƏÇİ YARAT
            Customers::create([
                'telegram_id' => $telegramId,
                'name' => $firstName,
                'surname' => $lastName,
                'telegram_username' => $username,
                'telegram_language' => $languageCode,
            ]);

            Log::info("Yeni Telegram istifadəçisi yaradıldı: $telegramId");
        } else {
            // ♻️ MÖVCUD İSTİFADƏÇİNİ YENİLƏ
            $customer->update([
                'name' => $firstName,
                'surname' => $lastName,
                'telegram_username' => $username,
                'telegram_language' => $languageCode,
            ]);

            Log::info("Telegram istifadəçisi yeniləndi: $telegramId");
        }
    }

    private function showStarPackages($chatId, $languageCode)
    {
        $packages = Packages::all();

        $keyboard = [];

        foreach ($packages as $pkg) {

            // Yığcam və gözəl düymə text-i
            $btnText = "{$pkg->telegram_emoji} {$pkg->getTranslation('name',$languageCode)} – {$pkg->scan_count} scans";

            // Saving varsa əlavə et (məs: -23%)
            if ($pkg->saving > 0) {
                $btnText .= " (−{$pkg->saving}%)";
            }

            // Stars qiymətini product_id_for_purchase-dan çıxar
            // example: "standard_package_700" → 700

            $btnText .= " – {$pkg->telegram_star_price} ⭐";

            // Inline button
            $keyboard[] = [
                ['text' => $btnText, 'callback_data' => "buy_" . $pkg->product_id_for_purchase]
            ];
        }

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "⭐ You have reached your scan limit.\nChoose a package below:",
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard
            ])
        ]);
    }

    private function callbackQueryForStarPackages($data, $chatId)
    {
        // CLICK HANDLER
        if (str_starts_with($data, 'buy_')) {

            $productId = str_replace('buy_', '', $data); // basic_40

            // DB-dən paket tap
            $package = Packages::where('product_id_for_purchase', $productId)->first();

            if (!$package) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Package not found."
                ]);
                return;
            }

            // INVOICE GÖNDƏR
            Telegram::sendInvoice([
                'chat_id' => $chatId,
                'title' => $package->name,
                'description' => "Unlock {$package->scan_count} additional scans in VitalScan.",
                'payload' => "pkg_{$package->id}",
                'provider_token' => '', // Stars üçün boş olmalıdır!
                'currency' => 'XTR', // Stars valyutası
                'prices' => [
                    ["label" => "{$package->scan_count} Scans", "amount" => intval($package->telegram_star_price)]
                ],
            ]);
        }
    }

    public function successPayment($update, $from)
    {
        $getCustomer = Customers::where('telegram_id',$from->getId())->first();
        $payment = $update['message']['successful_payment'];
        $payload = $payment['invoice_payload']; // pkg_12 (package id)
        $chatId = $update['message']['chat']['id'];

        // payload-dan package ID-ni çıxar: pkg_12 → 12
        $packageId = intval(str_replace('pkg_', '', $payload));

        // Mazadan paketi tap
        $package = Packages::find($packageId);

        if (!$package) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "❗ Payment received, but package not found.",
            ]);
            return;
        }

        // İstifadəçiyə əlavə scan sayı əlavə et — BURADA LOGİKA SƏNİN SİSTEMƏ GÖRƏ YAZILIR
        // məsələn:
        // User::where('telegram_id', $chatId)->increment('scan_balance', $package->scan_count);

        DB::transaction(function () use ($getCustomer, $package, $update, $payment) {
            $purchase = Subscription::create([
                'customer_id' => $getCustomer->id,
                'product_id' => $package->id,
                'platform' => 'telegram',
                'purchase_token' => $payment['telegram_payment_charge_id'],
                'start_date' => now(),
                'status' => SubscriptionStatus::ACTIVE->value,
                'payment_details' => json_encode($update),
                'amount' => $package['amount'],
            ]);

            CustomerPackages::create([
                'customer_id' => $getCustomer->id,
                'package_id' => $package->id,
                'remaining_scans' => $package->scan_count,
                'subscription_id' => $purchase->id,
                'status' => SubscriptionStatus::ACTIVE->value,
            ]);
        });

        // Uğurlu ödəniş mesajı
        $msg = "🎉 You have successfully purchased *{$package->scan_count} extra scans*!\n"
            . "✨ Package: *{$package->name}*";

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'Markdown'
        ]);
    }

    private function getProfileData($chatId, $from)
    {
        $getCustomer = Customers::where('telegram_id', $from->getId())->first();

        $msg = "👤 Your Profile

• *Name:* " . $getCustomer->name . " " . $getCustomer->surname . "
• *Username:* @" . $getCustomer->telegram_username . "
• *Credits:* 45
• *Premium:* No
• *Joined:* " . Carbon::parse($getCustomer->created_at)->format('d/m/Y') . "

Choose an action:";

        $keyboard = [
            [
                ['text' => 'Usage History', 'callback_data' => "usage_history"]
            ],
            [
                ['text' => 'Payment History', 'callback_data' => "payment_history"]
            ],
            [
                ['text' => 'Buy Package', 'callback_data' => "profile_buy_package"]
            ],
            [
                ['text' => 'Support', 'callback_data' => "support"]
            ],
            [
                ['text' => 'Back to Home', 'callback_data' => "back_home"]
            ],
        ];

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $msg,
            'resize_keyboard' => true,
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard
            ])
        ]);
    }

    private function getStaticPageData($chatId, $type = 'privacy')
    {
        if($type == 'privacy') {
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
        } elseif($type == 'terms') {
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
        } elseif($type == 'about_us') {
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

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML'
        ]);
    }

}
