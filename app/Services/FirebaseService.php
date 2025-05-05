<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FirebaseService
{
    protected $projectId;
    protected $accessToken;

    public function __construct()
    {
        $this->projectId = env('FIREBASE_PROJECT_ID');
        $this->accessToken = $this->getAccessToken();
    }

    protected function getAccessToken()
    {
        $credentialsPath = storage_path(env('FIREBASE_CREDENTIALS'));

        $client = new \Google\Client();
        $client->setAuthConfig($credentialsPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $token = $client->fetchAccessTokenWithAssertion();
        return $token['access_token'];
    }

    public function sendNotification($deviceToken, $title, $body, $data = [])
    {
        $accessToken = $this->getAccessToken();

        $response = $this->getPost($deviceToken, $title, $body, $data, $accessToken);

        return $response->json();
    }

    public function sendMulticastNotification(array $deviceTokens, string $title, string $body, array $data = [])
    {
        $accessToken = $this->getAccessToken();

        $responses = [];

        foreach ($deviceTokens as $token) {
            $response = $this->getPost($token, $title, $body, $data, $accessToken);

            $responses[] = $response->json();
        }

        return $responses;
    }

    /**
     * @param $deviceToken
     * @param $title
     * @param $body
     * @param mixed $data
     * @param mixed $accessToken
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function getPost($deviceToken, $title, $body, mixed $data, mixed $accessToken): \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
    {
        $message = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10', // 10 = High priority (Immediate)
                    ],
                ],
//                'data' => $data
            ],
        ];

        if (!empty($data)) {
            $message['message']['data'] = $data;
        }

        $response = Http::withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $message);
        return $response;
    }

}
