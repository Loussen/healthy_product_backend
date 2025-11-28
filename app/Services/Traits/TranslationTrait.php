<?php

namespace App\Services\Traits;

use App\Constants\TelegramConstants;

trait TranslationTrait
{
    private function mapLangNameToCode(string $languageName, bool $reverse = false): string
    {
        $map = TelegramConstants::LANGUAGE_MAP;

        if ($reverse) {
            $map = array_flip($map);
        }

        return $map[$languageName] ?? TelegramConstants::DEFAULT_LANGUAGE;
    }

    public function translate(string $type, array $data = [], string $languageCode = TelegramConstants::DEFAULT_LANGUAGE): array
    {
        $messages = [];

        // Hər bir tərcümə növü üçün mövcud məntiq
        if ($type == 'category') {
            $messages = [
                'az' => '📋 Kateqoriyalar',
                'en' => '📋 Categories',
                'ru' => '📋 Категории',
                'tr' => '📋 Kategoriler',
                'es_ES' => '📋 Categorías',
                'de_DE' => '📋 Kategorien',
            ];
        } elseif ($type == 'choose_category') {
            $messages = [
                'az' => "✅ Seçilmiş dil: *{$data['language_name']}*\n\nİndi kateqoriyanı seç 👇\n\nℹ️ Qeyd: Seçəcəyiniz kateqoriya məhsulun kateqoriyası deyil, sizə aid olan kateqoriyadır. Məsələn: *Vegetarian*",
                'en' => "✅ Selected language: *{$data['language_name']}*\n\nNow choose a category 👇\n\nℹ️ Note: The category you choose is *about you*, not the product. For example: *Vegetarian*",
                'ru' => "✅ Выбранный язык: *{$data['language_name']}*\n\nТеперь выберите категорию 👇\n\nℹ️ Примечание: Категория, которую вы выбираете, относится *к вам*, а не к продукту. Например: *Вегетарианец*",
                'tr' => "✅ Seçilen dil: *{$data['language_name']}*\n\nŞimdi bir kategori seç 👇\n\nℹ️ Not: Seçeceğiniz kategori ürünle ilgili değil, *sizinle* ilgilidir. Örneğin: *Vejetaryen*",
                'es_ES' => "✅ Idioma seleccionado: *{$data['language_name']}*\n\nAhora elige una categoría 👇\n\nℹ️ Nota: La categoría que elijas está *relacionada contigo*, no con el producto. Por ejemplo: *Vegetariano*",
                'de_DE' => "✅ Ausgewählte Sprache: *{$data['language_name']}*\n\nWähle jetzt eine Kategorie 👇\n\nℹ️ Hinweis: Die Kategorie, die du auswählst, bezieht sich *auf dich*, nicht auf das Produkt. Zum Beispiel: *Vegetarier*",
            ];
        } elseif ($type == 'choose_category_2') {
            $messages = [
                'az' => '🎯 Kateqoriyanı seç 👇',
                'en' => '🎯 Select a category 👇',
                'ru' => '🎯 Выберите категорию 👇',
                'tr' => '🎯 Kategori seç 👇',
                'es_ES' => '🎯 Selecciona una categoría 👇',
                'de_DE' => '🎯 Wähle eine Kategorie 👇',
            ];
        } elseif ($type == 'chosen_category') {
            $messages = [
                'az' => "✅ Seçdiyin kateqoriya: *{$data['category_name']}*\n\n📸 İndi məhsulun *tərkibi hissəsinin* şəklini göndər, analiz edək.",
                'en' => "✅ Selected category: *{$data['category_name']}*\n\n📸 Now send a photo of the *ingredients section* of the product for analysis.",
                'ru' => "✅ Выбранная категория: *{$data['category_name']}*\n\n📸 Теперь отправь фото *раздела с ингредиентами* продукта для анализа.",
                'tr' => "✅ Seçtiğin kategori: *{$data['category_name']}*\n\n📸 Şimdi ürünün *içindekiler kısmının* fotoğrafını gönder, analiz edelim.",
                'es_ES' => "✅ Categoría seleccionada: *{$data['category_name']}*\n\n📸 Ahora envía una foto de la *sección de ingredientes* del producto para analizarla.",
                'de_DE' => "✅ Ausgewählte Kategorie: *{$data['category_name']}*\n\n📸 Sende jetzt ein Foto des *Zutatenbereichs* des Produkts zur Analyse.",
            ];
        } elseif ($type == 'instruction_button') {
            $messages = [
                'az' => "💡 *Necə İstifadə Edilir?* (Təlimatlara Bax)",
                'en' => "💡 *How to Use the Bot* (Read Instructions)",
                'ru' => "💡 *Как Пользоваться Ботом* (Читать Инструкцию)",
                'tr' => "💡 *Nasıl Kullanılır?* (Talimatları Oku)",
                'es_ES' => "💡 *¿Cómo Usarlo?* (Leer Instrucciones)",
                'de_DE' => "💡 *Wie wird der Bot genutzt?* (Anleitung lesen)",
            ];
        } elseif ($type == 'please_wait') {
            $messages = [
                'az' => "🔍 Məhsul seçdiyiniz *dil* və *kateqoriya* üzrə analiz olunur...\n\nZəhmət olmasa gözləyin ⏳",
                'en' => "🔍 The product is being analyzed according to your selected *language* and *category*...\n\nPlease wait ⏳",
                'ru' => "🔍 Продукт анализируется согласно выбранным *языку* и *категории*...\n\nПожалуйста, подождите ⏳",
                'tr' => "🔍 Ürün seçtiğiniz *dil* ve *kategoriye* göre analiz ediliyor...\n\nLütfen bekleyin ⏳",
                'es_ES' => "🔍 El producto se está analizando según el *idioma* y la *categoría* seleccionados...\n\nPor favor, espere ⏳",
                'de_DE' => "🔍 Das Produkt wird basierend auf der ausgewählten *Sprache* und *Kategorie* analysiert...\n\nBitte warten Sie ⏳",
            ];
        } elseif ($type == 'back_home') {
            $messages = [
                'az' => "🏠 Ana menyuya qayıt",
                'en' => "🏠 Back to main menu",
                'ru' => "🏠 Вернуться в главное меню",
                'tr' => "🏠 Ana menüye dön",
                'es_ES' => "🏠 Volver al menú principal",
                'de_DE' => "🏠 Zur Hauptmenü zurückkehren",
            ];
        } elseif ($type == 'unexpected') {
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
        } elseif ($type == 'scan_limit') {
            $messages = [
                'az' => "🔔 Xəbərdarlıq!\n\nZəhmət olmasa məhsulun tərkib hissələrinin düzgün oxunduğuna əmin olun. Bir neçə uğursuz cəhddən sonra skan etmə prosesi müvəqqəti olaraq dayandırıla bilər.",
                'en' => "🔔 Warning!\n\nPlease make sure the product ingredients are read correctly. After several failed attempts, the scanning process may be temporarily suspended.",
                'ru' => "🔔 Предупреждение!\n\nПожалуйста, убедитесь, что состав продукта считывается правильно. После нескольких неудачных попыток процесс сканирования может быть временно приостановлен.",
                'tr' => "🔔 Uyarı!\n\nLütfen ürünün içerik bilgilerinin doğru okunduğundan emin olun. Birkaç başarısız denemeden sonra tarama işlemi geçici olarak durdurulabilir.",
                'es_ES' => "🔔 ¡Advertencia!\n\nAsegúrate de que los ingredientes del producto se lean correctamente. Tras varios intentos fallidos, el proceso de escaneo puede suspenderse temporalmente.",
                'de_DE' => "🔔 Warnung!\n\nBitte stellen Sie sicher, dass die Produktzutaten korrekt gelesen werden. Nach mehreren fehlgeschlagenen Versuchen kann der Scanvorgang vorübergehend ausgesetzt werden."
            ];
        } elseif ($type == 'out_of_scan') {
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
        } elseif ($type === 'out_of_scan_packages') {
            $messages = [
                'az' => "⭐ *Davam etmək üçün paket seçin*\nAşağıdakı paketlərdən birini seçərək analiz limitinizi artıra bilərsiniz.",
                'en' => "⭐ *Choose a package to continue*\nSelect a package below to increase your scan limit.",
                'ru' => "⭐ *Выберите пакет, чтобы продолжить*\nВыберите один из пакетов ниже, чтобы увеличить лимит сканирования.",
                'tr' => "⭐ *Devam etmek için bir paket seçin*\nAşağıdaki paketlerden birini seçerek tarama limitinizi artırabilirsiniz.",
                'es_ES' => "⭐ *Elige un paquete para continuar*\nSelecciona un paquete para aumentar tu límite de escaneos.",
                'de_DE' => "⭐ *Wähle ein Paket, um fortzufahren*\nWähle unten ein Paket, um dein Scanlimit zu erhöhen.",
            ];
        } elseif ($type == 'not_registered') {
            $messages = [
                'az' => "🚫 Qeydiyyatdan keçməmisiniz. Qeydiyyat üçün lütfən /start yazın.",
                'en' => "🚫 You are not registered. Please /start to register.",
                'ru' => "🚫 Вы не зарегистрированы. Пожалуйста, используйте /start для регистрации.",
                'tr' => "🚫 Kayıtlı değilsiniz. Lütfen kayıt olmak için /start komutunu kullanın.",
                'es_ES' => "🚫 No estás registrado. Por favor, usa /start para registrarte.",
                'de_DE' => "🚫 Sie sind nicht registriert. Bitte verwenden Sie /start zur Registrierung.",
            ];
        } elseif ($type == 'instruction') {
            $messages = [
                'az' => [
                    'title' => "📸 Analizə Necə Başlamalı?",
                    'instruction_text' => "VitalScan AI botundan istifadə etmək çox sadədir. Zəhmət olmasa aşağıdakı addımları izləyin:",
                    'steps' => [
                        "1️⃣ **Dil və Kateqoriya Seçin:** Bot ilə ünsiyyətə başlamaq üçün əvvəlcə istədiyiniz dili və analiz etmək istədiyiniz məhsulun kateqoriyasını seçin.",
                        "2️⃣ **Etiket Şəklini Çəkin:** Məhsulun **yalnız tərkiblərin** göstərildiyi hissəsinin aydın və yaxın plan şəklini çəkin. (Nümunə aşağıda)",
                        "3️⃣ **Şəkli Göndərin:** Çəkdiyiniz şəkli bota göndərin. Bir neçə saniyə ərzində tam analiz, Sağlamlıq Skoru və 'Qırmızı Bayraqlar' əldə edəcəksiniz.",
                    ],
                    'image_caption' => "✅ Yaxşı Şəkil Nümunəsi: Bütün tərkiblər aydın oxunur və yalnız tərkib hissəsi görünür. Zəhmət olmasa bu placeholder-i öz real etiket şəkilinizlə əvəz edin!",
                    'image_url' => "https://vitalscan.app/storage/ingredients_example.png",
                ],
                'en' => [
                    'title' => "📸 How to Start Analysis?",
                    'instruction_text' => "Using the VitalScan AI bot is very simple. Please follow the steps below:",
                    'steps' => [
                        "1️⃣ **Select Language & Category:** To begin interacting with the bot, first choose your desired language and the product category you want to analyze.",
                        "2️⃣ **Take Label Photo:** Take a clear, close-up photo of the product's section where **only the ingredients** are shown. (Example below)",
                        "3️⃣ **Send the Photo:** Send the captured photo to the bot. In a few seconds, you will receive a full analysis, Health Score, and 'Red Flags'.",
                    ],
                    'image_caption' => "✅ Good Photo Example: All ingredients are clearly readable and only the ingredient list is visible. Please replace this placeholder with your actual label image!",
                    'image_url' => "https://vitalscan.app/storage/ingredients_example.png",
                ],
                'ru' => [
                    'title' => "📸 Как начать анализ?",
                    'instruction_text' => "Использовать бот VitalScan AI очень просто. Пожалуйста, следуйте инструкциям ниже:",
                    'steps' => [
                        "1️⃣ **Выберите Язык и Категорию:** Для начала работы с ботом выберите желаемый язык и категорию продукта, который хотите анализировать.",
                        "2️⃣ **Сделайте Фото Этикетки:** Сделайте четкое фото крупным планом той части продукта, где указан **ТОЛЬКО состав**.",
                        "3️⃣ **Отправьте Фото:** Отправьте сделанное фото боту. Через несколько секунд вы получите полный анализ, Оценку Здоровья и 'Красные Флаги'.",
                    ],
                    'image_caption' => "✅ Пример Хорошего Фото: Все ингредиенты четко читаются, виден только список состава. Пожалуйста, замените этот плейсхолдер фактическим изображением этикетки!",
                    'image_url' => "https://vitalscan.app/storage/ingredients_example.png",
                ],
                'tr' => [
                    'title' => "📸 Analize Nasıl Başlanır?",
                    'instruction_text' => "VitalScan AI botunu kullanmak çok kolaydır. Lütfen aşağıdaki adımları izleyin:",
                    'steps' => [
                        "1️⃣ **Dil ve Kategori Seçin:** Bot ile etkileşime başlamak için öncelikle istediğiniz dili ve analiz etmek istediğiniz ürün kategorisini seçin.",
                        "2️⃣ **Etiket Fotoğrafı Çekin:** Ürünün **yalnızca içeriklerinin** gösterildiği bölümünün net ve yakın plan fotoğrafını çekin. (Örnek aşağıda)",
                        "3️⃣ **Fotoğrafı Gönderin:** Çektiğiniz fotoğrafı bota gönderin. Birkaç saniye içinde tam analiz, Sağlık Puanı ve 'Kırmızı Bayraklar' alacaksınız.",
                    ],
                    'image_caption' => "✅ İyi Fotoğraf Örneği: Tüm içerikler net bir şekilde okunabilir ve sadece içerik listesi görünür. Lütfen bu yer tutucuyu gerçek etiket görselinizle değiştirin!",
                    'image_url' => "https://vitalscan.app/storage/ingredients_example.png",
                ],
                // İspan və Alman dilləri
                'es' => [
                    'title' => "📸 ¿Cómo iniciar el análisis?",
                    'instruction_text' => "Usar el bot VitalScan AI es muy simple. Por favor, siga los pasos a continuación:",
                    'steps' => [
                        "1️⃣ **Seleccione Idioma y Categoría:** Para comenzar a interactuar con el bot, primero elija su idioma deseado y la categoría del producto que desea analizar.",
                        "2️⃣ **Tome una Foto de la Etiqueta:** Tome una foto clara y de primer plano de la sección del producto donde **solo se muestran los ingredientes** (Ejemplo abajo).",
                        "3️⃣ **Envíe la Foto:** Envíe la foto capturada al bot. En unos segundos, recibirá un análisis completo, Puntuación de Salud y 'Banderas Rojas'.",
                    ],
                    'image_caption' => "✅ Ejemplo de Buena Foto: Todos los ingredientes son claramente legibles y solo se ve la lista de ingredientes. ¡Reemplace este marcador de posición con su imagen de etiqueta real!",
                    'image_url' => "https://vitalscan.app/storage/ingredients_example.png",
                ],
                'de' => [
                    'title' => "📸 Wie starte ich die Analyse?",
                    'instruction_text' => "Die Verwendung des VitalScan AI Bots ist sehr einfach. Bitte folgen Sie den nachstehenden Schritten:",
                    'steps' => [
                        "1️⃣ **Sprache & Kategorie wählen:** Um mit dem Bot zu interagieren, wählen Sie zuerst Ihre gewünschte Sprache und die Produktkategorie, die Sie analysieren möchten.",
                        "2️⃣ **Etikettenfoto machen:** Machen Sie ein klares, nah aufgenommenes Foto des Produktabschnitts, auf dem **nur die Inhaltsstoffe** aufgeführt sind (Beispiel unten).",
                        "3️⃣ **Senden Sie das Foto:** Senden Sie das aufgenommene Foto an den Bot. In wenigen Sekunden erhalten Sie eine vollständige Analyse, den Gesundheits-Score und 'Rote Flaggen'.",
                    ],
                    'image_caption' => "✅ Gutes Fotobeispiel: Alle Inhaltsstoffe sind klar lesbar und nur die Inhaltsstoffliste ist sichtbar. Bitte ersetzen Sie diesen Platzhalter durch Ihr tatsächliches Etikettenbild!",
                    'image_url' => "https://vitalscan.app/storage/ingredients_example.png",
                ]
            ];
        } elseif ($type == 'scan_result') {
            $messages = [
                'az' =>
                    "✅ *Məhsul uğurla analiz edildi!*\n
🧾 *Məhsul:* {$data['product_name']}
📦 *Kateqoriya:* {$data['category']}
💯 *Sağlamlıq balı:* {$data['health_score']}

🧪 *Tərkibi:*
{$data['ingredients']}
🌿 *Ən Yaxşı Tərkiblər:*
{$data['best_ingredients']}
⚠️ *Ən Pis Tərkiblər:*
{$data['worst_ingredients']}
ℹ️ *Ətraflı:*
{$data['details']}

🕒 *Cavab vaxtı:* {$data['response_time']} ms\n",

                'en' =>
                    "✅ *Product scanned successfully!*\n
🧾 *Product:* {$data['product_name']}
📦 *Category:* {$data['category']}
💯 *Health Score:* {$data['health_score']}

🧪 *Ingredients:*
{$data['ingredients']}
🌿 *Best Ingredients:*
{$data['best_ingredients']}
⚠️ *Worst Ingredients:*
{$data['worst_ingredients']}
ℹ️ *Details:*
{$data['details']}

🕒 *Response time:* {$data['response_time']} ms\n",

                'ru' =>
                    "✅ *Продукт успешно проанализирован!*\n
🧾 *Продукт:* {$data['product_name']}
📦 *Категория:* {$data['category']}
💯 *Оценка здоровья:* {$data['health_score']}

🧪 *Ингредиенты:*
{$data['ingredients']}
🌿 *Лучшие Ингредиенты:*
{$data['best_ingredients']}
⚠️ *Худшие Ингредиенты:*
{$data['worst_ingredients']}
ℹ️ *Подробности:*
{$data['details']}

🕒 *Время ответа:* {$data['response_time']} мс\n",

                'tr' =>
                    "✅ *Ürün başarıyla analiz edildi!*\n
🧾 *Ürün:* {$data['product_name']}
📦 *Kategori:* {$data['category']}
💯 *Sağlık Skoru:* {$data['health_score']}

🧪 *İçindekiler:*
{$data['ingredients']}
🌿 *En İyi İçindekiler:*
{$data['best_ingredients']}
⚠️ *En Kötü İçindekiler:*
{$data['worst_ingredients']}
ℹ️ *Detaylar:*
{$data['details']}

🕒 *Yanıt süresi:* {$data['response_time']} ms\n",

                'es_ES' =>
                    "✅ *¡Producto analizado con éxito!*\n
🧾 *Producto:* {$data['product_name']}
📦 *Categoría:* {$data['category']}
💯 *Puntuación de salud:* {$data['health_score']}

🧪 *Ingredientes:*
{$data['ingredients']}
🌿 *Mejores Ingredientes:*
{$data['best_ingredients']}
⚠️ *Peores Ingredientes:*
{$data['worst_ingredients']}
ℹ️ *Detalles:*
{$data['details']}

🕒 *Tiempo de respuesta:* {$data['response_time']} ms\n",

                'de_DE' =>
                    "✅ *Produkt erfolgreich analysiert!*\n
🧾 *Produkt:* {$data['product_name']}
📦 *Kategorie:* {$data['category']}
💯 *Gesundheitspunktzahl:* {$data['health_score']}

🧪 *Zutaten:*
{$data['ingredients']}
🌿 *Beste Zutaten:*
{$data['best_ingredients']}
⚠️ *Schlechteste Zutaten:*
{$data['worst_ingredients']}
ℹ️ *Details:*
{$data['details']}

🕒 *Antwortzeit:* {$data['response_time']} ms\n",
            ];
        } elseif ($type == 'profile_menu') {
            $messages = [
                'az' => [
                    'title' => "👤 Profiliniz",
                    'name' => "Ad / Soyad",
                    'username' => "İstifadəçi Adı",
                    'credits' => "Qalan Skan Sayı",
                    'premium' => "Premium Status",
                    'joined' => "Qoşulma Tarixi",
                    'health_score' => "Sağlamlıq Skoru", // YENİ
                    'action' => "Bir əməliyyat seçin",
                    'usage' => "📊 İstifadə Tarixçəsi",
                    'payment' => "💳 Ödəniş Tarixçəsi",
                    'buy' => "⭐️ Paket Al",
                    'support' => "💬 Dəstək",
                    'back' => "🏠 Ana Səhifəyə Qayıt",
                    'yes' => 'Bəli',
                    'no' => 'Xeyr',
                    'my_packages' => '🎁 Aktiv Paketlərim',
                    'faucet_pay_email_status' => "FaucetPay E-poçt", // YENİ
                    'earn_menu' => "💰 Qazan", // YENİ: Menyu çıxarıldı
                    'not_set' => 'Təyin Edilməyib', // YENİ: FaucetPay durumu üçün
                ],
                'en' => [
                    'title' => "👤 Your Profile",
                    'name' => "Name / Surname",
                    'username' => "Username",
                    'credits' => "Remaining Scans",
                    'premium' => "Premium Status",
                    'joined' => "Joined Date",
                    'health_score' => "Health Score", // YENİ
                    'action' => "Choose an action",
                    'usage' => "📊 Usage History",
                    'payment' => "💳 Payment History",
                    'buy' => "⭐️ Buy Package",
                    'support' => "💬 Support",
                    'back' => "🏠 Back to Home",
                    'yes' => 'Yes',
                    'no' => 'No',
                    'my_packages' => '🎁 My Active Packages',
                    'faucet_pay_email_status' => "FaucetPay Email", // YENİ
                    'earn_menu' => "💰 Earn", // YENİ: Menu çıxarıldı
                    'not_set' => 'Not Set', // YENİ: FaucetPay durumu üçün
                ],
                'ru' => [
                    'title' => "👤 Ваш Профиль",
                    'name' => "Имя / Фамилия",
                    'username' => "Имя Пользователя",
                    'credits' => "Осталось Сканирований",
                    'premium' => "Премиум Статус",
                    'joined' => "Дата Присоединения",
                    'health_score' => "Оценка Здоровья", // YENİ
                    'action' => "Выберите действие",
                    'usage' => "📊 История Использования",
                    'payment' => "💳 История Платежей",
                    'buy' => "⭐️ Купить Пакет",
                    'support' => "💬 Поддержка",
                    'back' => "🏠 На Главную",
                    'yes' => 'Да',
                    'no' => 'Нет',
                    'my_packages' => '🎁 Мои Активные Пакеты',
                    'faucet_pay_email_status' => "FaucetPay Email", // YENİ
                    'earn_menu' => "💰 Заработать", // YENİ: Меню çıxarıldı
                    'not_set' => 'Не Установлено', // YENİ: FaucetPay durumu üçün
                ],
                'tr' => [
                    'title' => "👤 Profiliniz",
                    'name' => "Ad / Soyad",
                    'username' => "Kullanıcı Adı",
                    'credits' => "Kalan Tarama Sayısı",
                    'premium' => "Premium Durumu",
                    'joined' => "Katılma Tarihi",
                    'health_score' => "Sağlık Skoru", // YENİ
                    'action' => "Bir eylem seçin",
                    'usage' => "📊 Kullanım Geçmişi",
                    'payment' => "💳 Ödeme Geçmişi",
                    'buy' => "⭐️ Paket Satın Al",
                    'support' => "💬 Destek",
                    'back' => "🏠 Ana Sayfaya Dön",
                    'yes' => 'Evet',
                    'no' => 'Hayır',
                    'my_packages' => '🎁 Aktif Paketlerim',
                    'faucet_pay_email_status' => "FaucetPay E-posta", // YENİ
                    'earn_menu' => "💰 Kazan", // YENİ: Menüsü çıxarıldı
                    'not_set' => 'Ayarlanmadı', // YENİ: FaucetPay durumu üçün
                ],
                'es_ES' => [
                    'title' => "👤 Tu Perfil",
                    'name' => "Nombre / Apellido",
                    'username' => "Nombre de Usuario",
                    'credits' => "Escaneos Restantes",
                    'premium' => "Estado Premium",
                    'joined' => "Fecha de registro",
                    'health_score' => "Puntuación de Salud", // YENİ
                    'action' => "Elige una acción",
                    'usage' => "📊 Historial de Uso",
                    'payment' => "💳 Historial de Pagos",
                    'buy' => "⭐️ Comprar Paquete",
                    'support' => "💬 Soporte",
                    'back' => "🏠 Volver a Inicio",
                    'yes' => 'Sí',
                    'no' => 'No',
                    'my_packages' => '🎁 Mis Paquetes Activos',
                    'faucet_pay_email_status' => "Correo FaucetPay", // YENİ
                    'earn_menu' => "💰 Ganar", // YENİ: Menú çıxarıldı
                    'not_set' => 'No Establecido', // YENİ: FaucetPay durumu üçün
                ],
                'de_DE' => [
                    'title' => "👤 Ihr Profil",
                    'name' => "Name / Nachname",
                    'username' => "Benutzername",
                    'credits' => "Verbleibende Scans",
                    'premium' => "Premium Status",
                    'joined' => "Beitrittsdatum",
                    'health_score' => "Gesundheitspunktzahl", // YENİ
                    'action' => "Wählen Sie eine Aktion",
                    'usage' => "📊 Nutzungsverlauf",
                    'payment' => "💳 Zahlungsverlauf",
                    'buy' => "⭐️ Paket Kaufen",
                    'support' => "💬 Support",
                    'back' => "🏠 Zur Startseite",
                    'yes' => 'Ja',
                    'no' => 'Nein',
                    'my_packages' => '🎁 Meine Aktiven Pakete',
                    'faucet_pay_email_status' => "FaucetPay E-Mail", // YENİ
                    'earn_menu' => "💰 Verdienen", // YENİ: Menü çıxarıldı
                    'not_set' => 'Nicht Festgelegt', // YENİ: FaucetPay durumu üçün
                ],
            ];
        } elseif ($type == 'my_packages_list') {
            $messages = [
                'az' => [
                    'title' => "🎁 Aktiv Paketlərim",
                    'no_packages' => "Hazırda aktiv paketiniz yoxdur.",
                    'package_name' => "Paket Adı",
                    'remaining_scans' => "Qalan Skan",
                    'created_at' => "Başlama Tarixi", // YENİLƏNDİ
                    'back_instruction' => "_Profilə Geri düyməsi ilə əvvəlki səhifəyə qayıdın._",
                ],
                'en' => [
                    'title' => "🎁 My Active Packages",
                    'no_packages' => "You currently have no active packages.",
                    'package_name' => "Package Name",
                    'remaining_scans' => "Remaining Scans",
                    'created_at' => "Purchase Date", // YENİLƏNDİ
                    'back_instruction' => "_Use the Back to Profile button to return to the previous page._",
                ],
                'ru' => [
                    'title' => "🎁 Мои Активные Пакеты",
                    'no_packages' => "В настоящее время у вас нет активных пакетов.",
                    'package_name' => "Название Пакета",
                    'remaining_scans' => "Осталось Сканов",
                    'created_at' => "Дата Покупки", // YENİLƏNDİ
                    'back_instruction' => "_Вернитесь на предыдущую страницу с помощью кнопки «Назад к Профилю»._",
                ],
                'tr' => [
                    'title' => "🎁 Aktif Paketlerim",
                    'no_packages' => "Şu anda aktif bir paketiniz bulunmamaktadır.",
                    'package_name' => "Paket Adı",
                    'remaining_scans' => "Kalan Tarama",
                    'created_at' => "Başlangıç Tarihi", // YENİLƏNDİ
                    'back_instruction' => "_Profile Geri düğmesinden önceki sayfaya dönün._",
                ],
                'es_ES' => [
                    'title' => "🎁 Mis Paquetes Activos",
                    'no_packages' => "Actualmente no tienes paquetes activos.",
                    'package_name' => "Nombre del Paquete",
                    'remaining_scans' => "Escaneos Restantes",
                    'created_at' => "Fecha de Compra", // YENİLƏNDİ
                    'back_instruction' => "_Utilice el botón Volver al Perfil para regresar a la página anterior._",
                ],
                'de_DE' => [
                    'title' => "🎁 Meine Aktiven Pakete",
                    'no_packages' => "Sie haben derzeit keine aktiven Pakete.",
                    'package_name' => "Paketname",
                    'remaining_scans' => "Verbleibende Scans",
                    'created_at' => "Kaufdatum", // YENİLƏNDİ
                    'back_instruction' => "_Kehren Sie mit der Schaltfläche 'Zurück zum Profil' zur vorherigen Seite zurück._",
                ],
            ];
        } elseif ($type == 'image_not_readable') {
            $messages = [
                'az' => "⚠️ Foto oxuna bilmədi. Yenidən göndərin.",
                'en' => "⚠️ The photo could not be read. Please send it again.",
                'ru' => "⚠️ Фото не удалось прочитать. Отправьте его снова.",
                'tr' => "⚠️ Fotoğraf okunamadı. Lütfen tekrar gönderin.",
                'es_ES' => "⚠️ La foto no pudo ser leída. Por favor, envíala de nuevo.",
                'de_DE' => "⚠️ Das Foto konnte nicht gelesen werden. Bitte senden Sie es erneut.",
            ];
        } elseif ($type == 'payment_success') {
            $messages = [
                'az' => "🎉 Siz uğurla *{$data['scan_count']} əlavə skan* satın aldınız!\n✨ Paket: *{$data['package_name']}*",
                'en' => "🎉 You have successfully purchased *{$data['scan_count']} extra scans*!\n✨ Package: *{$data['package_name']}*",
                'ru' => "🎉 Вы успешно приобрели *{$data['scan_count']} дополнительных сканов*!\n✨ Пакет: *{$data['package_name']}*",
                'tr' => "🎉 Başarıyla *{$data['scan_count']} ek tarama* satın aldınız!\n✨ Paket: *{$data['package_name']}*",
                'es_ES' => "🎉 ¡Ha comprado con éxito *{$data['scan_count']} escaneos adicionales*!\n✨ Paquete: *{$data['package_name']}*",
                'de_DE' => "🎉 Sie haben erfolgreich *{$data['scan_count']} zusätzliche Scans* erworben!\n✨ Paket: *{$data['package_name']}*",
            ];
        } elseif ($type == 'payment_error') {
            $messages = [
                'az' => "❗ Ödəniş alındı, lakin paket tapılmadı.",
                'en' => "❗ Payment received, but package not found.",
                'ru' => "❗ Платёж получен, но пакет не найден.",      // ƏLAVƏ OLUNDU
                'tr' => "❗ Ödeme alındı, ancak paket bulunamadı.",     // ƏLAVƏ OLUNDU
                'es_ES' => "❗ Pago recibido, pero el paquete no fue encontrado.", // ƏLAVƏ OLUNDU
                'de_DE' => "❗ Zahlung erhalten, aber das Paket wurde nicht gefunden.", // ƏLAVƏ OLUNDU
            ];
        } elseif ($type == 'invoice') {
            $messages = [
                'az' => [
                    'description' => "VitalScan-da {$data['scan_count']} əlavə skan əldə edin.",
                    'label' => "{$data['scan_count']} Skan",
                ],
                'en' => [
                    'description' => "Unlock {$data['scan_count']} additional scans in VitalScan.",
                    'label' => "{$data['scan_count']} Scans",
                ],
                'ru' => [
                    'description' => "Разблокируйте {$data['scan_count']} дополнительных сканов в VitalScan.",
                    'label' => "{$data['scan_count']} Сканов",
                ],
                'tr' => [
                    'description' => "VitalScan'de {$data['scan_count']} ek tarama kilidini açın.",
                    'label' => "{$data['scan_count']} Tarama",
                ],
                'es_ES' => [
                    'description' => "Desbloquea {$data['scan_count']} escaneos adicionales en VitalScan.",
                    'label' => "{$data['scan_count']} Escaneos",
                ],
                'de_DE' => [
                    'description' => "Schalte {$data['scan_count']} zusätzliche Scans in VitalScan frei.",
                    'label' => "{$data['scan_count']} Scans",
                ],
            ];
        } elseif ($type == 'payment_history') {
            $messages = [
                'az' => [
                    'title' => "💳 Ödəniş Tarixçəsi",
                    'no_history' => "Ödəniş qeydi tapılmadı.",
                    'date' => "Tarix",
                    'package' => "Paket",
                    'amount' => "Məbləğ (Ulduz)",
                    'status' => "Status",
                    'active' => "✅ Aktiv",
                    'completed' => "🟢 Tamamlandı",
                    'back_to_profile' => "⬅️ Profilə Geri", // Düymənin mətni
                    'back_instruction' => "_⬅️ Profilə Geri_ düyməsindən geri qayıdın." // Tam təlimat mətni
                ],
                'en' => [
                    'title' => "💳 Payment History",
                    'no_history' => "No payment records found.",
                    'date' => "Date",
                    'package' => "Package",
                    'amount' => "Amount (Stars)",
                    'status' => "Status",
                    'active' => "✅ Active",
                    'completed' => "🟢 Completed",
                    'back_to_profile' => "⬅️ Back to Profile",
                    'back_instruction' => "_⬅️ Back to Profile_ button to return."
                ],
                'ru' => [
                    'title' => "💳 История Платежей",
                    'no_history' => "Записи о платежах не найдены.",
                    'date' => "Дата",
                    'package' => "Пакет",
                    'amount' => "Сумма (Звезды)",
                    'status' => "Статус",
                    'active' => "✅ Активно",
                    'completed' => "🟢 Завершено",
                    'back_to_profile' => "⬅️ Назад к Профилю",
                    'back_instruction' => "_⬅️ Назад к Профилю_ кнопкой, чтобы вернуться."
                ],
                'tr' => [
                    'title' => "💳 Ödeme Geçmişi",
                    'no_history' => "Ödeme kaydı bulunamadı.",
                    'date' => "Tarih",
                    'package' => "Paket",
                    'amount' => "Miktar (Yıldız)",
                    'status' => "Durum",
                    'active' => "✅ Aktif",
                    'completed' => "🟢 Tamamlandı",
                    'back_to_profile' => "⬅️ Profile Geri",
                    'back_instruction' => "_⬅️ Profile Geri_ düğmesinden geri dönün."
                ],
                'es_ES' => [
                    'title' => "💳 Historial de Pagos",
                    'no_history' => "No se encontraron registros de pago.",
                    'date' => "Fecha",
                    'package' => "Paquete",
                    'amount' => "Cantidad (Estrellas)",
                    'status' => "Estado",
                    'active' => "✅ Activo",
                    'completed' => "🟢 Completado",
                    'back_to_profile' => "⬅️ Volver al Perfil",
                    'back_instruction' => "_⬅️ Volver al Perfil_ botón para volver."
                ],
                'de_DE' => [
                    'title' => "💳 Zahlungsverlauf",
                    'no_history' => "Keine Zahlungsaufzeichnungen gefunden.",
                    'date' => "Datum",
                    'package' => "Paket",
                    'amount' => "Betrag (Sterne)",
                    'status' => "Status",
                    'active' => "✅ Aktiv",
                    'completed' => "🟢 Abgeschlossen",
                    'back_to_profile' => "⬅️ Zurück zum Profil",
                    'back_instruction' => "_⬅️ Zurück zum Profil_ Taste, um zurückzukehren."
                ],
            ];
        } elseif ($type == 'usage_history') {
            $messages = [
                'az' => [
                    'title' => "📊 İstifadə Tarixçəsi",
                    'no_history' => "Skan qeydi tapılmadı.",
                    'date' => "Tarix",
                    'product' => "Məhsul",
                    'score' => "Sağlamlıq Balı",
                    'time' => "Cavab Vaxtı",
                    'back_to_profile' => "⬅️ Profilə Geri",
                    'back_instruction' => "_⬅️ Profilə Geri_ düyməsindən geri qayıdın."
                ],
                'en' => [
                    'title' => "📊 Usage History",
                    'no_history' => "No scan records found.",
                    'date' => "Date",
                    'product' => "Product",
                    'score' => "Health Score",
                    'time' => "Response Time",
                    'back_to_profile' => "⬅️ Back to Profile",
                    'back_instruction' => "_⬅️ Back to Profile_ button to return."
                ],
                'ru' => [
                    'title' => "📊 История Использования",
                    'no_history' => "Записи сканирования не найдены.",
                    'date' => "Дата",
                    'product' => "Продукт",
                    'score' => "Оценка Здоровья",
                    'time' => "Время Ответа",
                    'back_to_profile' => "⬅️ Назад к Профилю",
                    'back_instruction' => "_⬅️ Назад к Профилю_ кнопкой, чтобы вернуться."
                ],
                'tr' => [
                    'title' => "📊 Kullanım Geçmişi",
                    'no_history' => "Tarama kaydı bulunamadı.",
                    'date' => "Tarih",
                    'product' => "Ürün",
                    'score' => "Sağlık Skoru",
                    'time' => "Yanıt Süresi",
                    'back_to_profile' => "⬅️ Profile Geri",
                    'back_instruction' => "_⬅️ Profile Geri_ düğmesinden geri dönün."
                ],
                'es_ES' => [
                    'title' => "📊 Historial de Uso",
                    'no_history' => "No se encontraron registros de escaneo.",
                    'date' => "Fecha",
                    'product' => "Producto",
                    'score' => "Puntuación de Salud",
                    'time' => "Tiempo de Respuesta",
                    'back_to_profile' => "⬅️ Volver al Perfil",
                    'back_instruction' => "_⬅️ Volver al Perfil_ botón para volver."
                ],
                'de_DE' => [
                    'title' => "📊 Nutzungsverlauf",
                    'no_history' => "Keine Scan-Aufzeichnungen gefunden.",
                    'date' => "Datum",
                    'product' => "Produkt",
                    'score' => "Gesundheitspunktzahl",
                    'time' => "Antwortzeit",
                    'back_to_profile' => "⬅️ Zurück zum Profil",
                    'back_instruction' => "_⬅️ Zurück zum Profil_ Taste, um zurückzukehren."
                ],
            ];
        }

        return $messages;
    }
}
