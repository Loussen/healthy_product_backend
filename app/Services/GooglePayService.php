<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\AndroidPublisher;

class GooglePayService
{
    protected $client;
    protected $publisher;

    public function __construct()
    {
        $jsonPath = storage_path('app/vital-scan-vscan-908399013c6f.json');

        if (!file_exists($jsonPath)) {
            throw new \Exception('Service account JSON file not found.');
        }

        $this->client = new GoogleClient();
        $this->client->setAuthConfig($jsonPath);
        $this->client->addScope('https://www.googleapis.com/auth/androidpublisher');
        $this->client->setApplicationName('VScan');
        $this->client->setAccessType('offline');

        $this->publisher = new AndroidPublisher($this->client);
    }

    public function verifySubscription(string $productId, string $purchaseToken)
    {
        return $this->publisher->purchases_subscriptions->get(
            'com.healthyproduct.app',
            $productId,
            $purchaseToken
        );
    }
}
