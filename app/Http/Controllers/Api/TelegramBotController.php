<?php

namespace App\Http\Controllers\Api;

use App\Constants\TelegramConstants;
use App\Models\Customers;
use App\Models\Packages;
use App\Services\DebugWithTelegramService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\CallbackQuery;

class TelegramBotController extends BaseController
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function handleWebhook(Request $request)
    {
        $update = Telegram::commandsHandler(true);

        $callback = $update->callback_query ?? null;
        $message = $update->getMessage();

        // 1. PRE-CHECKOUT APPROVE
        if (!empty($update['pre_checkout_query'])) {
            Telegram::answerPreCheckoutQuery(['pre_checkout_query_id' => $update['pre_checkout_query']['id'], 'ok' => true]);
            return response('Pre-checkout answered', 200);
        }

        if (!$message) {
            return response('No message', 200);
        }

//        if ($update->my_chat_member) {
//            Log::info("Bot chat member status changed: ".$update->my_chat_member->new_chat_member->status);
//            return response('OK', 200);
//        }

        $from = $this->getSenderFromUpdate($update);
        if (!$from) {
            $log = new DebugWithTelegramService();
            $log->debug("Could not retrieve sender data \n".$update);
            Log::info("Could not retrieve sender data \n".$update);
            return response('Could not retrieve sender data', 200);
        }

        // İstifadçini sinxronlaşdır
        $customer = $this->telegramService->syncTelegramUser($from, $update);

        $chatId = $message->chat->id ?? ($callback->message->chat->id ?? null);
        $text = trim($message->getText() ?? '');
        $data = $callback['data'] ?? '';

        // 2. ÖDƏNİŞİN UĞURLU OLMASI
        if (!empty($update['message']['successful_payment'])) {
            $this->telegramService->handleSuccessfulPayment($update, $from);
            return response()->json(['ok' => true]);
        }

        // 3. CALLBACK SORĞULARI
        if (!empty($callback)) {
            if (str_starts_with($data, TelegramConstants::CALLBACK_BUY_PREFIX)) {
                $this->handleBuyPackageCallback($data, $chatId);
                return response()->json(['ok' => true]);
            }

            if (str_starts_with($data, TelegramConstants::CALLBACK_LANGUAGE_PREFIX)) {
                $this->telegramService->handleLanguageSelection($chatId, $data, $from);
                $this->checkLanguageAndShowCategories($chatId, $from);
                return response()->json(['ok' => true]);
            }

            if (str_starts_with($data, TelegramConstants::CALLBACK_CATEGORY_PREFIX)) {
                $this->telegramService->handleCategorySelection($chatId, $data, $from);
                return response()->json(['ok' => true]);
            }

            if ($data == "choose_language" || $data == 'back_home') {
                $this->telegramService->showLanguageSelection($chatId);
                return response()->json(['ok' => true]);
            }

            if ($data == "choose_category") {
                $this->checkLanguageAndShowCategories($chatId, $from);
                return response()->json(['ok' => true]);
            }

            // Profil menyusunda yerləşən lakin implementasiyası mövcud olmayan düymələr:
            if ($data == 'profile_buy_package') {
                $this->telegramService->showStarPackages($chatId, $customer->language ?? TelegramConstants::DEFAULT_LANGUAGE);
                return response()->json(['ok' => true]);
            }

            if($data == 'usage') {
                $this->telegramService->showUsage($chatId,$customer->language ?? TelegramConstants::DEFAULT_LANGUAGE,$from);
                return response()->json(['ok' => true]);
            }

            if($data == 'support') {
                $this->telegramService->sendSupportLink($chatId,$customer->language ?? TelegramConstants::DEFAULT_LANGUAGE);
                return response()->json(['ok' => true]);
            }

            if ($data === 'payment_history') {
                $this->telegramService->sendPaymentHistory($chatId, $from);
                return response()->json(['ok' => true]);
            }

            if ($data === 'profile') {
                $this->telegramService->getProfileData($chatId, $from);
                return response()->json(['ok' => true]);
            }

            if ($data === 'usage_history') {
                $this->telegramService->sendUsageHistory($chatId, $from);
                return response()->json(['ok' => true]);
            }

            if ($data === 'my_packages_list') {
                $this->telegramService->sendMyPackagesList($chatId, $from);
                return response()->json(['ok' => true]);
            }

            if (str_starts_with($data, 'ton_buy_')) {
                $productId = str_replace('ton_buy_', '', $data);
                $package = Packages::where('product_id_for_purchase', $productId)->first();

                if ($package) {
                    // YENİ METODU ÇAĞIRIRIQ
                    $this->telegramService->sendTonInvoice($chatId, $package);
                    return response()->json(['ok' => true]);
                }
            }
            // ... usage_history, payment_history, support kimi digər callback-lər də buraya əlavə oluna bilər.
        }

        // 4. MƏTN ƏMRLƏRİ
        switch ($text) {
            case TelegramConstants::COMMAND_START:
                $this->telegramService->sendWelcomeMessage($chatId, $from->getFirstName());
                $this->telegramService->showLanguageSelection($chatId);
                break;
            case TelegramConstants::COMMAND_LANGUAGE:
                $this->telegramService->showLanguageSelection($chatId);
                break;
            case TelegramConstants::COMMAND_CATEGORY:
                $this->checkLanguageAndShowCategories($chatId, $from);
                break;
            case TelegramConstants::COMMAND_PROFILE:
                $this->telegramService->getProfileData($chatId, $from);
                break;
            case TelegramConstants::COMMAND_PACKAGES:
                $this->telegramService->showStarPackages($chatId, $customer->language ?? TelegramConstants::DEFAULT_LANGUAGE);
                break;
            case TelegramConstants::COMMAND_PRIVACY:
            case TelegramConstants::COMMAND_TERMS:
            case TelegramConstants::COMMAND_ABOUT_US:
                $this->telegramService->getStaticPageData($chatId, ltrim($text, '/'));
                break;
            case TelegramConstants::COMMAND_SUPPORT_US: // Yeni əmr
                $this->telegramService->sendSupportLink($chatId, $customer->language ?? TelegramConstants::DEFAULT_LANGUAGE);
                break;
            case TelegramConstants::COMMAND_USAGE_HISTORY: // Yeni əmr
                $this->telegramService->sendUsageHistory($chatId, $from);
                break;
            case TelegramConstants::COMMAND_PAYMENT_HISTORY: // Yeni əmr
                $this->telegramService->sendPaymentHistory($chatId, $from);
                break;
            case TelegramConstants::COMMAND_INSTRUCTION: // Yeni əmr
                $this->telegramService->sendInstruction($chatId, $from);
                break;
            default:
                // 5. ŞƏKİL GÖNDƏRİLMƏSİ
                if ($message->has('photo')) {
                    $this->handleImageFlow($chatId, $message, $from);
                    break;
                }
                // 6. QEYRİ-MÜƏYYƏN CAVAB
                $this->sendUnexpectedMessage($chatId, $customer->language ?? TelegramConstants::DEFAULT_LANGUAGE);
                break;
        }

        return response()->json(['ok' => true]);
    }

    // --- KÖMƏKÇİ METODLAR ---

    private function getSenderFromUpdate($update)
    {
        if ($update->message) {
            $from = $update->message->from;
        } elseif ($update->callback_query) {
            $from = $update->callback_query->from;
        }
//        elseif ($update->my_chat_member) {
//            $from = $update->my_chat_member->from;
//        }
        else {
            return null;
        }

        if ($from && $from->is_bot && !empty($update->callback_query)) {
            return $update->callback_query->from;
        }

        return $from;
    }

    private function handleBuyPackageCallback(string $data, int $chatId): void
    {
        $update = $this->getCurrentUpdate(); // Update obyektini almaq üçün bəzi xüsusi kod lazım ola bilər,
        // lakin mövcud controller strukturu ilə aşağıdakı kimi düzəldirik:
        $from = $this->getSenderFromUpdate($update);
        $customer = $this->telegramService->getCustomerByFrom($from);
        $languageCode = $customer->language ?? TelegramConstants::DEFAULT_LANGUAGE;


        $productId = str_replace(TelegramConstants::CALLBACK_BUY_PREFIX, '', $data);
        $package = Packages::where('product_id_for_purchase', $productId)->first();

        if (!$package) {
            $this->telegramService->sendMessage($chatId, "Package not found.");
            return;
        }

        // DÜZƏLİŞ BURADADIR: Dil kodunu ötürün
        $this->telegramService->sendInvoice($chatId, $package, $languageCode);
    }

    private function getCurrentUpdate() {
        return Telegram::getWebhookUpdate();
    }

    private function checkLanguageAndShowCategories(int $chatId, $from): void
    {
        $getCustomer = $this->telegramService->getCustomerByFrom($from);

        if (!$getCustomer || !$getCustomer->language) {
            $this->telegramService->showLanguageSelection($chatId);
            return;
        }

        $this->telegramService->showCategories($chatId, $from);
    }

    private function handleImageFlow(int $chatId, $message, $from): void
    {
        $getCustomer = $this->telegramService->getCustomerByFrom($from);

        if (!$getCustomer || !$getCustomer->language) {
            $this->telegramService->showLanguageSelection($chatId);
            return;
        }

        if (!$getCustomer->default_category_id) {
            $this->telegramService->showCategories($chatId, $from);
            return;
        }

        $this->telegramService->handleProductImage($chatId, $message, $from);
    }

    private function sendUnexpectedMessage(int $chatId, string $languageCode): void
    {
        $getWord = $this->telegramService->translate('unexpected');

        $keyboard = [
            [['text' => '🌍 Choose a language', 'callback_data' => "choose_language"]],
            [['text' => '🎯 Choose a category', 'callback_data' => "choose_category"]],
        ];

        $this->telegramService->sendMessage(
            $chatId,
            $getWord[$languageCode],
            null,
            ['inline_keyboard' => $keyboard]
        );
    }
}
