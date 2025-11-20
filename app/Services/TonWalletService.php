<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class TonWalletService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $botUsername;

    public function __construct()
    {
        $this->baseUrl = Config::get('services.wallet_pay.base_url');
        $this->apiKey = Config::get('services.wallet_pay.api_key');
        $this->botUsername = Config::get('services.wallet_pay.bot_username');
    }

    /**
     * Wallet Pay API vasitəsilə TON ödəniş linki yaradır.
     * * @param float $amount TON məbləği (nöqtədən sonra ən azı 2 rəqəm)
     * @param string $currency Valyuta (Məsələn: TON)
     * @param string $payload Xarici ID (Ödəniş uğurlu olduqda geri qayıdan unikal identifier)
     * @param string $description Məhsulun adı
     * @return string|null Ödəniş linki (payLink) və ya null
     */
    public function createTonInvoice(
        float $amount,
        string $currency,
        string $payload,
        string $description
    ): ?string {

        if (empty($this->apiKey)) {
            Log::error('Wallet Pay API Key konfiqurasiya edilməyib.');
            return null;
        }

        // 1. Düzəliş BURADADIR: Wallet Pay-in tələb etdiyi dəqiq endpoint-i yoxlayın.
        // Fərz edək ki, "createOrder" istifadə edirlər (sənədlərdə ən çox rast gəlinən forma).
        $endpoint = "{$this->baseUrl}/createOrder";

        // Tələb olunan məlumatlar
        $data = [
            // API 100-ə vurulmuş int tələb etdiyi üçün, float-ı string-ə çeviririk
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'description' => $description,
            'external_id' => $payload,
            'timeout_seconds' => 3600, // Ödənişin bitmə müddəti (1 saat)
            'bot_username' => $this->botUsername,
            // 'return_url' => '...', // Uğurlu ödənişdən sonra geri yönləndirmə URL-i əlavə etmək olar
        ];

        try {
            // Debug üçün API Açarını Log etməyə ehtiyac yoxdur, çünki bu, TƏHLÜKƏSİZLİK RİSKİ yaradır.
            // Əvvəlki mesajınızdakı Log::info("API_KEY: ".$this->apiKey); sətirini buraxırıq.

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
            ])->post($endpoint, $data);

            // Response-u Log edin (sadəcə statusu yoxlayın, bütün cavabı log etməyin)
            Log::info("Wallet Pay Response Status: " . $response->status());


            // API cavabının yoxlanılması
            if ($response->successful() && isset($response->json()['data']['payLink'])) {

                Log::info('Wallet Pay ödəniş linki uğurla yaradıldı.', ['payload' => $payload, 'response' => $response->json()]);

                return $response->json()['data']['payLink'];
            }

            // Əgər status 400-499 aralığındadırsa, səbəbi log edin
            $errorDetails = $response->json();
            $errorMessage = $errorDetails['message'] ?? $response->body();


            // Uğursuz API cavabı və ya cavab strukturunun səhv olması
            Log::error('Wallet Pay API səhvi.', [
                'status' => $response->status(),
                'message' => $errorMessage,
                'payload' => $data,
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            // Bağlantı və ya digər gözlənilməz xətalar
            Log::error('Wallet Pay API çağırışı zamanı gözlənilməz xəta: ' . $e->getMessage());
            return null;
        }
    }
}
