<?php

namespace App\Http\Controllers\Api;

use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramBotController extends BaseController
{
    /**
     * @throws TelegramSDKException
     */
    public function test()
    {
        $response = Telegram::bot('mybot')->getMe();

        dd($response);
    }
}
