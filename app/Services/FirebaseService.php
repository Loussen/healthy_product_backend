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

        $message = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ],
        ];

        if (!empty($data)) {
            $message['message']['data'] = $data;
        }

        $response = Http::withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $message);

        return $response->json();
    }

    public function sendMulticastNotification(array $deviceTokens, string $title, string $body, array $data = [])
    {
        $accessToken = $this->getAccessToken();

        $responses = [];

        foreach ($deviceTokens as $token) {
            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                ],
            ];

            if (!empty($data)) {
                $message['message']['data'] = $data;
            }

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $message);

            $responses[] = $response->json();
        }

        return $responses;
    }

}
