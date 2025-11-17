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
        } elseif ($type == 'scan_result') {
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
}
