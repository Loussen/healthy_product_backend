<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramBotController extends BaseController
{
    public function test()
    {
        $updates = Telegram::getUpdates();

        dd($updates);
    }
}
