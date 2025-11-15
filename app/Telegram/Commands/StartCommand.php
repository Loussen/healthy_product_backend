<?php

namespace App\Telegram\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

class StartCommand extends Command
{
    protected string $name = 'start';
    protected string $description = 'Start the bot and show menu';

    public function handle()
    {
        dd('sddsas');
        $keyboard = Keyboard::make([
            'keyboard' => [
                ['Kategoriler 📚'], // buraya istediğin kadar buton ekleyebilirsin
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ]);

        $this->replyWithMessage([
            'text' => "Hey, there! 👋\nWelcome to our bot!\n\nAşağıdakı 'Kateqoriyalar' düyməsindən başlaya bilərsən 👇",
            'reply_markup' => $keyboard,
        ]);
    }
}
