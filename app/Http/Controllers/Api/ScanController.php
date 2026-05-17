<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Models\Categories;
use App\Models\ScanResults;
use App\Services\DebugWithTelegramService;
use App\Services\GoogleVisionService;
use App\Services\ScanAiPromptService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use OpenAI;

class ScanController extends BaseController
{
    public function scanTest(Request $request): JsonResponse
    {
        return $this->sendResponse([
            'controller' => 'ScanController',
            'user_id' => $request->user()?->id,
            'openai_key_set' => !empty(config('services.openai.api_key')),
            'free_limit' => config('services.free_package_limit'),
            'php_version' => PHP_VERSION,
        ], 'ScanController is reachable');
    }

    public function scan(Request $request, GoogleVisionService $googleVisionService): JsonResponse
    {
        Log::info('ScanController@scan reached', ['user_id' => $request->user()?->id]);

        try {
            $startTime = microtime(true);

            $user = $request->user();

            // Validate the request
            $validator = Validator::make($request->all(), [
                'category_id' => 'required|numeric|exists:categories,id',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $language = $user->language ?? 'en';

            if ($validator->fails()) {
                return $this->sendError('validation_error', $validator->errors(), 400);
            }

            // Find oldest active package with remaining scans (FIFO: use oldest first)
            $activePackage = $user->packages()
                ->where('status', SubscriptionStatus::ACTIVE->value)
                ->where('remaining_scans', '>', 0)
                ->orderBy('id')
                ->first();

            $allScans = $user->scan_results()->count();
            $permitScan = $activePackage || $allScans < config('services.free_package_limit');

            if (!$permitScan) {
                return $this->sendError('out_of_scan_limit', 'Out of scan limit', 403);
            }

            $log = new DebugWithTelegramService();

            $key = 'scan_limit_for_unchecked_' . $user->email;
            $attempts = Cache::get($key, 0);

//            if ($attempts >= 5) {
//                $log->debug('Scan limit for unchecked: '.$user->email);
//                return $this->sendError("scan_limit_unreached_error", "Scan limit reached!
//You've temporarily reached your scan limit due to an unrecognized or unclear image. Please try again in a few moments and ensure the product ingredient image is clear and readable.", 429);
//            }

            // Handle file upload
            if ($request->hasFile('image')) {
                // Store the image
                $path = $request->file('image')->store('scan_results', 'public');

                $fullUrl = asset('storage/' . $path);

//                $content = $googleVisionService->extractText($fullUrl);

                // Category context for AI: compliance vs health depends on this lens
                $category = Categories::find($request->category_id);
                $categoryNameEn = $category->getTranslation('name', 'en')
                    ?: $category->getTranslation('name', $language)
                    ?: 'General';
                $categorySlugEn = $category->getTranslation('slug', 'en')
                    ?: ($category->slug ?? 'general');
                $categoryDescriptionEn = strip_tags(
                    $category->getTranslation('description', 'en') ?: ''
                );

                // Get image content as base64
//                $image = base64_encode(file_get_contents($request->file('image')));

                // Call OpenAI API
                $apiKey = config('services.openai.api_key');
                if (empty($apiKey)) {
                    Log::error('OpenAI API key is not configured', [
                        'config_value' => $apiKey,
                        'env_value' => env('OPENAI_API_KEY') ? 'SET' : 'NOT SET',
                    ]);
                    return $this->sendError('config_error', 'OpenAI API key is not configured. Please contact support.', 500);
                }
                $openai = OpenAI::client($apiKey);

                $scanUserPrompt = ScanAiPromptService::userScanInstruction(
                    $categoryNameEn,
                    $categorySlugEn,
                    $categoryDescriptionEn,
                    $language,
                );

//                $aiResponse = $openai->chat()->create([
//                    'model' => env('OPENAI_MODEL'),
//                    'temperature' => 0.0,
//                    'messages' => [
//                        [
//                            'role' => 'system',
//                            'content' => <<<EOT
//                                You are a product analysis system.
//
//                                Analyze the content provided by the user and return a structured JSON response.
//
//                                Rules:
//                                1. Detect the **actual product name** and **product category** from the content itself. Do NOT rely on or copy the category provided by the user. If product name or category cannot be determined, return `null` for them.
//                                2. Analyze the ingredients and dynamically calculate a **health score** according to the category specified by the user (e.g., Children, Adults, Diabetics, Allergic people). For example, a product that is healthy in general may be unhealthy for children or allergic individuals.
//                                3. Always respond in the **language specified by the user** (including product name, category, ingredients, score, etc.).
//                                4. If valid information is found, include `"check": true`. If important data is missing or cannot be interpreted, set `"check": false`.
//
//                                Return the result in this exact JSON format:
//                                {
//                                  "check": true or false,
//                                  "product_name": "Detected product name in the user's language or null",
//                                  "category": "Detected product category in the user's language or null",
//                                  "ingredients": ["List of all ingredients in the user's language"],
//                                  "worst_ingredients": ["List of worst ingredients for health, in user's language"],
//                                  "best_ingredients": ["List of best ingredients for health, in user's language"],
//                                  "health_score": "A percentage score based on the category specified by the user",
//                                  "detail_text": "Detailed explanation in the user's language, summarizing health evaluation"
//                                }
//                                EOT
//                        ],
//                        [
//                            'role' => 'user',
//                            'content' => [
//                                [
//                                    'type' => 'text',
//                                    'text' => "Analyze the contents of this product and respond in the specified JSON format.
//Write the ingredients (all, worst, best), health score (based on category: **$categoryName**), product name, product category, and detailed explanation in **$language**.
//Category: **$categoryName**, Language: **$language**."
//                                ],
//                                [
//                                    'type' => 'text',
//                                    'text' => $content
//                                ]
//                            ]
//                        ]
//                    ],
//                    'response_format' => ['type' => 'json_object'],
//                ]);

                $aiResponse = $openai->chat()->create([
                    'model' => config('services.openai.vision_model', 'gpt-4o-mini'),
                    'temperature' => 0.0,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => ScanAiPromptService::systemPrompt(),
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $scanUserPrompt,
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $fullUrl
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

                $aiResponseData = json_decode($aiResponse->choices[0]->message->content, true);

                $endTime = microtime(true);
                $responseTimeMs = (int)(($endTime - $startTime) * 1000); // milliseconds

                // Create scan result record
                $scanResult = ScanResults::create([
                    'customer_id' => $user->id,
                    'category_id' => $request->category_id,
                    'image' => $path,
                    'response' => $aiResponseData,
                    'category_name_ai' => $aiResponseData['category'] ?? '',
//                    'product_name_ai' => $aiResponseData['product_name'] && $aiResponseData['product_name'] != 'null' ? $aiResponseData['product_name'] : '',
                    'product_name_ai' => $aiResponseData['product_name'] ?? '',
                    'product_score' => isset($aiResponseData['health_score']) && $aiResponseData['health_score'] !== 'null'
                        ? (int) str_replace('%', '', $aiResponseData['health_score'])
                        : null,
                    'check' => $aiResponseData['check'],
//                    'ocr_text' => $content,
                    'response_time' => $responseTimeMs,
                ]);

                if(!$aiResponseData['check']) {
                    Cache::put($key, $attempts + 1, now()->addMinutes(5));
                    // Do NOT decrement remaining_scans for unclear/failed scans - only successful scans count
                    return $this->sendError("scan_unreached_error", "Warning!
Please make sure the product ingredients are read correctly. After several failed attempts, the scanning process may be temporarily suspended.", 429);
                }

                if($aiResponseData['check'] && $activePackage)
                {
                    $activePackage->decrement('remaining_scans');
                }

                $log->debug('Scaned! Customer: '.$user->name." ".$user->surname);

                return $this->sendResponse([
                    'scan_id' => $scanResult->id,
                    'image_path' => Storage::url($path),
                    'category_id' => $scanResult->category_id,
                    'response' => $scanResult->response
                ], 'Scan result uploaded successfully');
            }

            return $this->sendError('upload_error', 'No image file provided', 400);

        } catch (\Throwable $e) {
            Log::error('ScanController@scan error', [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            error_log('ScanController@scan THROWABLE: ' . get_class($e) . ' - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            try {
                $log = new DebugWithTelegramService();
                $log->debug([
                    'error' => 'ScanController@scan',
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);
            } catch (\Throwable $telegramError) {
                Log::warning('Failed to send scan error to Telegram', ['error' => $telegramError->getMessage()]);
            }

            return $this->sendError('scan_result_error', 'Scan result error - ' . $e->getMessage(), 500);
        }
    }

    public function getScanResult($scanId,Request $request): JsonResponse
    {
        $user = $request->user();

        $getScan = ScanResults::find($scanId);

        if (!$getScan || $getScan->customer_id !== $user->id) {
            return $this->sendError('not_found', 'Scan result not found', 404);
        }

        return $this->sendResponse($getScan, 'Scan result uploaded successfully');
    }

    public function getScanHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        // Filtreleme parametrelerini al
        $scoreFilter = $request->input('score_filter'); // 'high', 'low', 'all'
        $dateFilter = $request->input('date_filter'); // 'today', 'week', 'month', 'all'
        $favoritesOnly = $request->boolean('favorites_only', false);
        $categoryId = $request->input('category_id'); // Kategori filtresi eklendi

        // Sıralama parametrelerini al
        $sortBy = $request->input('sort_by', 'date'); // 'date' veya 'score'
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' veya 'desc'

        // Sayfalama parametrelerini al
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        // Ana sorguyu oluştur
        $query = ScanResults::where('customer_id', $user->id)
            ->with('category');

        // Kategori filtresini uygula
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Skor filtresini uygula
        if ($scoreFilter) {
            switch ($scoreFilter) {
                case 'high':
                    $query->where('product_score', '>', 50);
                    break;
                case 'low':
                    $query->where('product_score', '<=', 50);
                    break;
            }
        }

        // Tarih filtresini uygula
        if ($dateFilter) {
            switch ($dateFilter) {
                case 'today':
                    $query->whereDate('created_at', Carbon::today());
                    break;
                case 'week':
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                    break;
            }
        }

        // Favorileri filtrele
        if ($favoritesOnly) {
            $favoritedIds = $user->favoriteScanResults()->pluck('scan_result_id');
            $query->whereIn('id', $favoritedIds);
        }

        switch ($sortBy) {
            case 'score':
                $query->orderBy('product_score', $sortOrder);
                break;
            case 'date':
            default:
                $query->orderBy('created_at', $sortOrder);
                break;
        }

        // Sonuçları en yeniden en eskiye doğru sırala
        $query->orderBy('created_at', 'desc');

        // Sayfalama uygula
        $getScanResults = $query->paginate($perPage, ['*'], 'page', $page);

        // Favori taramaları getir
        $favoritedScanIds = $user->favoriteScanResults()
            ->pluck('scan_result_id')
            ->toArray();

        // Her tarama sonucuna is_favorite flag'ini ekle
        $resultsWithFavorites = $getScanResults->map(function ($scanResult) use ($favoritedScanIds) {
            $scanResult->is_favorite = in_array($scanResult->id, $favoritedScanIds);
            return $scanResult;
        });

        $total = $getScanResults->total();
        $lastPage = $total > 0 ? $getScanResults->lastPage() : 0;

        $response = [
            'data' => $resultsWithFavorites,
            'pagination' => [
                'current_page' => $getScanResults->currentPage(),
                'last_page' => $lastPage,
                'per_page' => $getScanResults->perPage(),
                'total' => $total,
            ]
        ];

        return $this->sendResponse($response, 'success');
    }

    public function toggleFavorite(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = validator($request->all(), [
            'scan_result_id' => 'required|exists:scan_results,id',
            'action' => 'required|in:favorite,unfavorite',
        ]);

        if ($validator->fails()) {
            return $this->sendError('validation_error', $validator->errors()->first(), 422);
        }

        $scanResultId = $request->scan_result_id;
        $action = $request->action;

        $scanResult = ScanResults::find($scanResultId);

        if (!$scanResult) {
            return $this->sendError('not_found', 'Scan result not found');
        }

        if ($scanResult->customer_id !== $user->id) {
            return $this->sendError('unauthorized', 'You are not authorized to perform this action', 403);
        }

        if ($action === 'favorite') {
            if (!$user->favoriteScanResults()->where('scan_result_id', $scanResultId)->exists()) {
                $user->favoriteScanResults()->attach($scanResultId);
                return $this->sendResponse(['favorited' => true], 'Scan result added to favorites');
            }
            return $this->sendResponse(['favorited' => true], 'Scan result already in favorites');

        } else {
            if ($user->favoriteScanResults()->where('scan_result_id', $scanResultId)->exists()) {
                $user->favoriteScanResults()->detach($scanResultId);
                return $this->sendResponse(['favorited' => false], 'Scan result removed from favorites');
            }
            return $this->sendResponse(['favorited' => false], 'Scan result was not in favorites');
        }
    }
}
