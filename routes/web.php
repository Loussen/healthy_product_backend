<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

Route::get('/test-mail', function () {
    \Illuminate\Support\Facades\Mail::raw('Test mesajı', function ($message) {
        $message->to('fhesenli55@gmail.com')
            ->subject('Test Email');
    });

    return 'Mail gönderildi!';
});
