<?php

namespace App\Services;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AppStoreVerificationService
{
    private $bundleId;
    private $issuerId;
    private $keyId;
    private $privateKey;

    public function __construct()
    {
        $this->bundleId = config('services.apple.bundle_id');
        $this->issuerId = config('services.apple.issuer_id');
        $this->keyId = config('services.apple.key_id');

        // Private key'i dosyadan oku
        $keyPath = storage_path('app/' . config('services.apple.private_key_path'));

        if (!file_exists($keyPath)) {
            throw new \Exception('Apple private key file not found: ' . $keyPath);
        }

        $this->privateKey = file_get_contents($keyPath);

        // Key formatını kontrol et
        if (strpos($this->privateKey, 'BEGIN PRIVATE KEY') === false) {
            throw new \Exception('Invalid private key format');
        }
    }

    public function verifyPurchase(string $receiptData, string $transactionId)
    {
        // Sandbox mı Production mı?
//        $environment = config('app.env') === 'production'
//            ? 'https://api.storekit.itunes.apple.com'
//            : 'https://api.storekit-sandbox.itunes.apple.com';

        $environment = 'https://api.storekit-sandbox.itunes.apple.com';

        $jwt = $this->generateJWT();

        Log::info('App Store API Request', [
            'environment' => $environment,
            'transactionId' => $transactionId,
        ]);

        $client = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);

        try {
            $response = $client->get("{$environment}/inApps/v1/transactions/{$transactionId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $jwt,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('App Store API Response', ['data' => $data]);

            // signedTransactionInfo'yu decode et
            $transactionInfo = $this->decodeJWS($data['signedTransactionInfo']);

            return (object) [
                'isValid' => true,
                'transactionId' => $transactionInfo['transactionId'] ?? $transactionId,
                'originalTransactionId' => $transactionInfo['originalTransactionId'] ?? null,
                'productId' => $transactionInfo['productId'] ?? null,
                'purchaseDate' => $transactionInfo['purchaseDate'] ?? null,
                'expiresDate' => $transactionInfo['expiresDate'] ?? null,
                'environment' => $transactionInfo['environment'] ?? 'Sandbox',
            ];

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();

            Log::error('App Store API Client Error', [
                'status' => $statusCode,
                'body' => $body,
            ]);

            throw new \Exception("App Store API error ({$statusCode}): {$body}");

        } catch (\Exception $e) {
            Log::error('App Store verification failed', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('App Store verification failed: ' . $e->getMessage());
        }
    }

    private function generateJWT(): string
    {
        $now = time();

        $payload = [
            'iss' => $this->issuerId,
            'iat' => $now,
            'exp' => $now + 3600,
            'aud' => 'appstoreconnect-v1',
            'bid' => $this->bundleId,
        ];

        try {
            // OpenSSL ile key'i parse et
            $key = openssl_pkey_get_private($this->privateKey);

            if ($key === false) {
                throw new \Exception('Failed to parse private key: ' . openssl_error_string());
            }

            return JWT::encode($payload, $this->privateKey, 'ES256', $this->keyId);

        } catch (\Exception $e) {
            Log::error('JWT generation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function decodeJWS(string $jws): array
    {
        $parts = explode('.', $jws);

        if (count($parts) !== 3) {
            throw new \Exception('Invalid JWS format');
        }

        // Base64url decode
        $payload = $parts[1];
        $payload = str_replace(['-', '_'], ['+', '/'], $payload);
        $payload = base64_decode($payload);

        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to decode JWS payload');
        }

        return $decoded;
    }
}
