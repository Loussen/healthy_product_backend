<?php

namespace App\Http\Controllers\Api;

use App\Models\Categories;
use App\Models\Customers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\CallbackQuery;

class TelegramBotController extends BaseController
{
    public function handleWebhook(Request $request)
    {
        $update = Telegram::commandsHandler(true);
        Log::info($update);
        $message = $update->getMessage();
        Log::info($message);

        $updateInfo = json_decode($update,true);
        Log::info($updateInfo['message']['from']['id']);

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

        // ChatId'yi doğrudan al (get('id') veya get('id')'yi kullan)
        $chatId = $chat->get('id') ?? $chat['id'];

        // $from nesnesini alırken de güvenli erişimi kullanın
        $from = $message->get('from');

        // User create or update
        $this->syncTelegramUser($from);

        $text = trim($message->getText() ?? '');

        // 🟢 1️⃣ /start → dil seçimi
        if ($text === '/start') {
            return $this->sendWelcomeMessage($chatId, $from->getFirstName());
        }

        // 🟡 Dil seçimi menyusu
        if ($text === '🌍 Language' || $text === '/language') {
            return $this->showLanguageSelection($chatId);
        }

        // 🟠 Dil seçilib
        if ($this->isLanguageSelected($text)) {
            return $this->handleLanguageSelection($chatId, $text);
        }

        Log::info(Cache::get("user_language_$chatId"));

        // 🟣 Kateqoriya menyusu
        $categoryTranslations = $this->translate('category');
        if (in_array($text, $categoryTranslations, true) || $text === '/category') {
            $language = Cache::get("user_language_$chatId");
            if(!$language) {
                return $this->showLanguageSelection($chatId);
            }
            return $this->showCategories($chatId);
        }

        // 🔵 Kateqoriya seçilib
        if ($this->isCategorySelected($text)) {
            return $this->handleCategorySelection($chatId, $text);
        }

        // 🟤 Şəkil göndərilibsə
        if ($message->has('photo')) {
            $language = Cache::get("user_language_$chatId");
            if(!$language) {
                return $this->showLanguageSelection($chatId);
            }
            $category = Cache::get("user_category_$chatId");
            if(!$category) {
                return $this->showCategories($chatId);
            }
            return $this->handleProductImage($chatId, $message);
        }

        $backHomeTranslations = $this->translate('back_home');
        if (in_array($text, $backHomeTranslations, true)) {
            $this->showLanguageSelection($chatId);
        }

        $language = Cache::get("user_language_$chatId", 'English');
        $language = preg_replace('/^\W+\s*/u', '', $language);
        $languageCode = $this->mapLangNameToCode($language);
        $getWord = $this->translate('unexpected');

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $getWord[$languageCode],
            'parse_mode' => 'Markdown'
        ]);

        return response()->json(['ok' => true]);
    }

    // ✅ 1️⃣ Xoş gəldin mesajı
    private function sendWelcomeMessage($chatId, $name): void
    {
        $keyboard = Keyboard::make([
            'keyboard' => [[Keyboard::button('🌍 Language')]],
            'resize_keyboard' => true,
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "👋 Hello, *{$name}!*
    Welcome to the Vital Scan - Product Analysis System.
    Please select your language 👇",
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    // ✅ 2️⃣ Dillərin siyahısı
    private function showLanguageSelection($chatId): void
    {
        $languages = collect([
//            ['code' => 'az', 'flag' => '🇦🇿', 'name' => 'Azerbaijani'],
            ['code' => 'en', 'flag' => '🇬🇧', 'name' => 'English'],
            ['code' => 'ru', 'flag' => '🇷🇺', 'name' => 'Russian'],
            ['code' => 'es_ES', 'flag' => '🇪🇸', 'name' => 'Spanish'],
            ['code' => 'de_DE', 'flag' => '🇩🇪', 'name' => 'German'],
            ['code' => 'tr', 'flag' => '🇹🇷', 'name' => 'Turkish'],
        ]);

        Cache::put('languages_list', $languages, now()->addMinutes(30));

        $buttons = [];
        // Dilleri ikişerli satırlara bölüyoruz
        foreach ($languages->chunk(2) as $chunk) {
            $row = [];
            // Her bir dil için bir düğme oluşturup o anki satıra ekliyoruz
            foreach ($chunk as $lang) {
                $row[] = Keyboard::button("{$lang['flag']} {$lang['name']}");
            }
            // Satırı ana düğmeler dizisine ekliyoruz
            $buttons[] = $row;
        }

        $keyboard = Keyboard::make([
            'keyboard' => $buttons, // Şimdi bu kesinlikle Array of Arrays
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "🌍 First, please select your language 👇",
            'reply_markup' => $keyboard,
        ]);
    }

    private function isLanguageSelected($text): bool
    {
        $languages = Cache::get('languages_list', collect());
        return $languages->contains(fn($lang) => str_contains($text, $lang['name']));
    }

    private function handleLanguageSelection($chatId, $languageName): void
    {
        Cache::put("user_language_$chatId", $languageName, now()->addHour());

        $language = Cache::get("user_language_$chatId", 'English');
        $language = preg_replace('/^\W+\s*/u', '', $language);
        $languageCode = $this->mapLangNameToCode($language);
        $getWord = $this->translate('category');

        $keyboard = Keyboard::make([
            'keyboard' => [[Keyboard::button($getWord[$languageCode])]],
            'resize_keyboard' => true,
        ]);

        $language = Cache::get("user_language_$chatId", 'English');
        $language = preg_replace('/^\W+\s*/u', '', $language);
        $languageCode = $this->mapLangNameToCode($language);
        $data['language_name'] = $languageName;
        $getWord = $this->translate('choose_category',$data);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $getWord[$languageCode],
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
            'x§' => true
        ]);
    }

    // ✅ 3️⃣ Kateqoriyalar
    private function showCategories($chatId): void
    {
        $language = Cache::get("user_language_$chatId", 'English');
        $language = preg_replace('/^\W+\s*/u', '', $language);
        $langCode = $this->mapLangNameToCode($language);

        $categories = Categories::all()->map(function ($category) use ($langCode) {
            return [
                'id' => $category->id,
                'name' => $category->getTranslation('name', $langCode) ?? 'Unknown',
                'emoji' => $category->emoji ?? '📁',
            ];
        });

        Cache::put('categories_list', $categories, now()->addMinutes(30));

        $buttons = [];

        foreach ($categories->chunk(2) as $chunk) {
            $row = [];
            // Her bir dil için bir düğme oluşturup o anki satıra ekliyoruz
            foreach ($chunk as $c) {
                $row[] = Keyboard::button("{$c['emoji']} {$c['name']}");
            }
            // Satırı ana düğmeler dizisine ekliyoruz
            $buttons[] = $row;
        }

        $language = Cache::get("user_language_$chatId", 'English');
        $language = preg_replace('/^\W+\s*/u', '', $language);
        $languageCode = $this->mapLangNameToCode($language);

        $getWord = $this->translate('back_home');

        $keyboard = Keyboard::make([
            'keyboard' => array_merge($buttons, [[Keyboard::button($getWord[$languageCode])]]),
            'resize_keyboard' => true,
        ]);


        $getWord = $this->translate('choose_category_2');

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $getWord[$languageCode],
            'reply_markup' => $keyboard,
        ]);
    }

    private function isCategorySelected($text): bool
    {
        $categories = Cache::get('categories_list', collect());
        return $categories->contains(fn($c) => str_contains($text, $c['name']));
    }

    private function handleCategorySelection($chatId, $categoryName): void
    {
        Cache::put("user_category_$chatId", $categoryName, now()->addHour());

        $language = Cache::get("user_language_$chatId", 'English');
        $language = preg_replace('/^\W+\s*/u', '', $language);
        $languageCode = $this->mapLangNameToCode($language);
        $data['category_name'] = $categoryName;
        $getWord = $this->translate('send_photo',$data);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $getWord[$languageCode],
            'parse_mode' => 'Markdown',
        ]);
    }

    // ✅ 4️⃣ Foto analiz
    private function handleProductImage($chatId, $message): void
    {
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

        $categoryName = Cache::get("user_category_$chatId", 'General');
        $language = Cache::get("user_language_$chatId", 'English');
        $languageCode = preg_replace('/^\W+\s*/u', '', $language);
        $languageCode = $this->mapLangNameToCode($languageCode);

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

        $ingredients = $data['ingredients'] ?? [];
        $best = $data['best_ingredients'] ?? [];
        $worst = $data['worst_ingredients'] ?? [];
        $detailText = $data['detail_text'] ?? [];

        // Liste biçimine çevir
        $ingredientsText = !empty($ingredients) ? "🧪 *Ingredients:*\n" . implode(", ", $ingredients) . "\n\n" : '';
        $bestText = !empty($best) ? "🌿 *Best Ingredients:*\n" . "• " . implode("\n• ", $best) . "\n\n" : '';
        $worstText = !empty($worst) ? "⚠️ *Worst Ingredients:*\n" . "• " . implode("\n• ", $worst) . "\n\n" : '';
        $detailText = !empty($detailText) ? "ℹ️ *Details:*\n" . "• " . $detailText . "\n\n" : '';

        $text =
            "✅ *Product scanned successfully!*\n\n" .
            "🧾 *Product:* " . ($data['product_name'] ?? 'Unknown') . "\n" .
            "📦 *Category:* " . ($categoryName ?? $data['category'] ) . "\n" .
            "💯 *Health Score:* " . ($data['health_score'] ?? 'N/A') . "\n" .
            "🕒 *Response time:* {$timeMs} ms\n\n" .
            $ingredientsText .
            $bestText .
            $worstText.
            $detailText;

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    private function mapLangNameToCode($languageName): string
    {
        $map = [
            'Azerbaijani' => 'az',
            'English' => 'en',
            'Russian' => 'ru',
            'Turkish' => 'tr',
            'Spanish' => 'es_ES',
            'German' => 'de_DE',
        ];
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
        } elseif($type == 'send_photo') {
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
                'az' => "🔙 Ana menyuya qayıt",
                'en' => "🔙 Back to main menu",
                'ru' => "🔙 Вернуться в главное меню",
                'tr' => "🔙 Ana menüye dön",
                'es_ES' => "🔙 Volver al menú principal",
                'de_DE' => "🔙 Zur Hauptmenü zurückkehren",
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

}
