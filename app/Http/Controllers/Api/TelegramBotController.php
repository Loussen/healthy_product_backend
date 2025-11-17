<?php

namespace App\Http\Controllers\Api;

use App\Constants\TelegramConstants;
use App\Models\Customers;
use App\Models\Packages;
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
        Log::info($update);

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

        $from = $this->getSenderFromUpdate($update);
        if (!$from) {
            Log::error('Göndərən (from) tapılmadı.');
            return response('Could not retrieve sender data', 200);
        }

        // İstifadçini sinxronlaşdır
        $customer = $this->telegramService->syncTelegramUser($from);

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
        } else {
            return null;
        }

        if ($from && $from->is_bot && !empty($update->callback_query)) {
            return $update->callback_query->from;
        }

        return $from;
    }

    private function handleBuyPackageCallback(string $data, int $chatId): void
    {
        $productId = str_replace(TelegramConstants::CALLBACK_BUY_PREFIX, '', $data);
        $package = Packages::where('product_id_for_purchase', $productId)->first();

        if (!$package) {
            $this->telegramService->sendMessage($chatId, "Package not found.");
            return;
        }

        $this->telegramService->sendInvoice($chatId, $package);
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
            [['text' => 'Choose a language', 'callback_data' => "choose_language"]],
            [['text' => 'Choose a category', 'callback_data' => "choose_category"]],
        ];

        $this->telegramService->sendMessage(
            $chatId,
            $getWord[$languageCode],
            null,
            ['inline_keyboard' => $keyboard]
        );
    }
}
