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
        $url = "https://api.telegram.org/bot$this->token/".__FUNCTION__."?$params";
        curl_setopt_array($curl, [CURLOPT_URL => $url]);
        curl_exec($curl);
        curl_close($curl);
    }
}
