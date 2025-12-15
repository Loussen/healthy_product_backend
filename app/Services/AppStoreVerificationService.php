<?php

namespace App\Services;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;

class AppStoreVerificationService
{
    private $bundleId;
    private $issuerId;
    private $keyId;
    private $privateKey;

    public function __construct()
    {
        $this->bundleId = config('services.apple.bundle_id'); // com.vscan.vitalscan
        $this->issuerId = config('services.apple.issuer_id');
        $this->keyId = config('services.apple.key_id');
        $this->privateKey = config('services.apple.private_key');
    }

    public function verifyPurchase(string $receiptData, string $transactionId)
    {
        // App Store Server API kullanarak doğrulama
        // Sandbox: https://api.storekit-sandbox.itunes.apple.com
        // Production: https://api.storekit.itunes.apple.com

        $environment = config('app.env') === 'production'
            ? 'https://api.storekit.itunes.apple.com'
            : 'https://api.storekit-sandbox.itunes.apple.com';

        $jwt = $this->generateJWT();

        $client = new Client();

        try {
            // Transaction bilgisini al
            $response = $client->get("{$environment}/inApps/v1/transactions/{$transactionId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $jwt,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            // signedTransactionInfo'yu decode et
            $transactionInfo = $this->decodeJWS($data['signedTransactionInfo']);

            return (object) [
                'isValid' => true,
                'transactionId' => $transactionInfo['transactionId'],
                'originalTransactionId' => $transactionInfo['originalTransactionId'],
                'productId' => $transactionInfo['productId'],
                'purchaseDate' => $transactionInfo['purchaseDate'],
                'expiresDate' => $transactionInfo['expiresDate'] ?? null,
                'environment' => $transactionInfo['environment'],
            ];
        } catch (\Exception $e) {
            throw new \Exception('App Store verification failed: ' . $e->getMessage());
        }
    }

    private function generateJWT(): string
    {
        $now = time();
        $payload = [
            'iss' => $this->issuerId,
            'iat' => $now,
            'exp' => $now + 3600, // 1 saat geçerli
            'aud' => 'appstoreconnect-v1',
            'bid' => $this->bundleId,
        ];

        return JWT::encode($payload, $this->privateKey, 'ES256', $this->keyId);
    }

    private function decodeJWS(string $jws): array
    {
        // JWS'yi decode et (Apple'ın signed response'u)
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid JWS format');
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'));
        return json_decode($payload, true);
    }
}
