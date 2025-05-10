<?php

namespace App\Services;

class DebugWithTelegramService
{
    private string $token   =  '7732936567:AAFOoegW9Q1HfwkCAz99Kt7wAsOm4TnjtFo';
    private string $chat_id =  '-1002296927964';

    public function debug($data): void
    {
        $info = [
            'chat_id'    => $this->chat_id,
            'parse_mode' => 'html',
            'text'       => '<pre>'.json_encode($data).'</pre>'
            //'text'    => $data
        ];
        $params = http_build_query($info, '', '&');
        $this->sendMessage($params);

    }
    private function sendMessage($params): void
    {
        $curl = curl_init();
        $url = "https://api.telegram.org/bot{$this->token}/sendMessage?$params";

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true, // 👈 cevabı bastırma, değişkene al
            CURLOPT_TIMEOUT        => 10,   // isteğe bağlı: timeout ayarı
        ]);

        $response = curl_exec($curl); // 👈 çıktı bastırılmaz, sadece yakalanır
        curl_close($curl);

        // İstersen loglama amaçlı cevabı da yazabilirsin
        // file_put_contents('telegram_response.log', $response);
    }
}
