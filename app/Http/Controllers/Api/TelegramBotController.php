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
        $update = Telegram::commandsHandler(true);

        $message = $update->getMessage();
        $chat_id = $message->getChat()->getId();

        Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Hello!'
        ]);
    }

    public function test()
    {
        dd(Telegram::getWebhookInfo());
    }
}
