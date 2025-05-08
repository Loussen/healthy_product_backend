<?php

namespace App\Services;

class GooglePayVerificationService
{
    private $packageName;
    private $serviceAccount;
    private $client;

    public function __construct()
    {
        $this->packageName = 'com.healthyproduct.app';
        $this->serviceAccount = storage_path('app/vital-scan-vscan-908399013c6f.json');

        $this->client = new \Google_Client();
        $this->client->setAuthConfig($this->serviceAccount);
        $this->client->addScope('https://www.googleapis.com/auth/androidpublisher');
    }

    public function verifyPurchase(string $productId, string $purchaseToken)
    {
        $log = new DebugWithTelegramService();
        try {
            $androidPublisher = new \Google_Service_AndroidPublisher($this->client);

            return $androidPublisher->purchases_products->get(
                $this->packageName,
                $productId,
                $purchaseToken
            );
        } catch (\Google_Service_Exception $e) {
            $log->debug('Google pay error: '.$e->getMessage());
            throw new \Exception('Failed to verify purchase with Google Play: ' . $e->getMessage());
        }
    }
}
