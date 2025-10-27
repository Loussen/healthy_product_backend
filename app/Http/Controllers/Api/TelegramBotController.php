<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramBotController extends BaseController
{
    /**
     * @throws TelegramSDKException
     */
    public function handleWebhook(Request $request)
    {
        $telegram = new Api(config('telegram.bots.mybot.token'));

        $update = $telegram->getWebhookUpdate();
        $message = $update->getMessage();
        $text = $message->getText();
        $chatId = $message->getChat()->getId();

        if ($text === '/start') {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "👋 Salam! Bot-a xoş gəldin.\nBuradan başlayırıq!"
            ]);
        }

        return response('ok');
    }
}
