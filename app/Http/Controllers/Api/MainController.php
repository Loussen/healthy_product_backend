<?php

namespace App\Http\Controllers\Api;

use App\Enums\GoogleNotificationType;
use App\Enums\SubscriptionStatus;
use App\Models\BugReports;
use App\Models\Categories;
use App\Models\ContactUs;
use App\Models\Countries;
use App\Models\CustomerPackages;
use App\Models\Customers;
use App\Models\DeviceToken;
use App\Models\Packages;
use App\Models\Page;
use App\Models\PushNotification;
use App\Models\ScanResults;
use App\Models\Subscription;
use App\Services\DebugWithTelegramService;
use App\Services\GooglePayService;
use App\Services\GooglePayVerificationService;
use App\Services\GoogleVisionService;
use Carbon\Carbon;
//use Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient;
//use Google\Cloud\AIPlatform\V1\PredictRequest;
//use Google\Protobuf\Struct;
//use Google\Protobuf\Value;
use Google\Service\AndroidPublisher;
use Google_Service_AndroidPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use OpenAI;
//use Google\Cloud\AIPlatform\V1\Content;
//use Google\Cloud\AIPlatform\V1\GenerationConfig;
//use Google\Cloud\AIPlatform\V1\Part;
//use Google\Cloud\AIPlatform\V1\Types\InlineData;
//use Google\Cloud\AIPlatform\V1\Types\GenerateContentRequest;
//use Google\Cloud\AIPlatform\V1\Types\GenerateContentResponse;
//use Google\Cloud\AIPlatform\V1\Types\Part\Data;
//use Google\Cloud\AIPlatform\V1\Types\Part\InlineData\MimeType;
//use Google\Cloud\AIPlatform\V1\GenerativeServiceClient;
use Google\Client as GoogleClient;


class MainController extends BaseController
{
    public function categories(Request $request): JsonResponse
    {
        try {
            $locale = $request->header('Accept-Language', 'en');

            $categories = Categories::all()->map(function ($category) use ($locale) {
                return [
                    'id' => $category->id,
                    'name' => $category->getTranslation('name',$locale) ?? 'Unknown',
                    'icon' => $category->icon ?? 'category',
                    'slug' => $category->getTranslation('slug',$locale) ?? 'unknown',
                    'color' => hexToMaterialColor($category->color ?? '#9E9E9E'),
                    'main_color' => $category->color ?? '#9E9E9E',
                    'description' => strip_tags($category->getTranslation('description',$locale)) ?? 'Null',
                ];
            });

            return $this->sendResponse($categories,'success');

        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('get_category_error', "Category error - ".$e->getMessage(), 500);
        }
    }

    public function customer(Request $request): JsonResponse
    {
        $locale = $request->header('Accept-Language', 'en');

        $user = $request->user();
        $getCustomer = Customers::with('scan_results')->find($user->id);

        if (is_null($getCustomer)) {
            return $this->sendError('customer_not_found', 'Customer not found');
        }

        $highestScoreScan = $getCustomer->scan_results()->where('product_score','>',0)->orderByDesc('product_score')->with('category')->first();
        $lowestScoreScan = $getCustomer->scan_results()
            ->where('product_score','>',0)
            ->orderBy('product_score')
            ->with('category')
            ->first();

        if ($highestScoreScan) {
            $highestScoreScan->product_name_ai = $highestScoreScan->product_name_ai ?: ($highestScoreScan->category_name_ai ?: 'Unspecified');
        }

        if ($lowestScoreScan) {
            $lowestScoreScan->product_name_ai = $lowestScoreScan->product_name_ai ?: ($lowestScoreScan->category_name_ai ?: 'Unspecified');
        }

        $thisMonthScans = $getCustomer->scan_results()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $todayScans = $getCustomer->scan_results()
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $allScans = $getCustomer->scan_results()
            ->count();

        $averageHealthScore = $getCustomer->scan_results()->where('product_score', '>', 0)->avg('product_score');

        $getCustomer->makeHidden(['scan_results']);

        $activePackage = $user->packages()
            ->where('created_at', '>=', now()->subMonth())
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->orderByDesc('id')
            ->first();

        $activePackageArray = $activePackage ? $activePackage->toArray() : null;

        if($activePackage)
        {
//            $activePackage['package'] = $activePackage->package;

            $activePackageArray['package'] = [
                'id' => $activePackage->package->id,
                'name' => $activePackage->package->getTranslation('name', $locale) ?? 'Unknown',
                'color' => $activePackage->package->color,
                'price' => $activePackage->package->price,
                'scan_count' => $activePackage->package->scan_count,
                'per_scan' => $activePackage->package->per_scan,
                'saving' => $activePackage->package->saving,
                'is_popular' => $activePackage->package->is_popular,
                'created_at' => $activePackage->package->created_at,
                'updated_at' => $activePackage->package->updated_at
            ];
        }

        return $this->sendResponse(
            array_merge($getCustomer->toArray(), [
                'highest_score_scan' => $highestScoreScan,
                'lowest_score_scan' => $lowestScoreScan,
                'monthly_scans' => $thisMonthScans,
                'daily_scans' => $todayScans,
                'free_scan_limit' => config('services.free_package_limit'),
                'usage_limit' => $allScans > config('services.free_package_limit') ? config('services.free_package_limit') : $allScans,
                'active_package' => $activePackageArray,
                'permit_scan' => ($activePackage && $activePackage->remaining_scans > 0) || $allScans < config('services.free_package_limit'),
                'average_health_score' => round($averageHealthScore) ?? 0,
            ]),
            'success'
        );
    }

    public function scanOld(Request $request): JsonResponse
    {
        try {
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

            $activePackage = $user->packages()
                ->where('remaining_scans', '>', 0)
                ->where('created_at', '>=', now()->subMonth())
                ->where('status', SubscriptionStatus::ACTIVE->value)
                ->orderBy('id')
                ->first();

            if(!$activePackage) {
                $allScans = $user->scan_results()
                    ->count();

                if($allScans >= config('services.free_package_limit')) {
                    return $this->sendError('out_of_scan_limit', 'Out of scan limit');
                }
            }

            // Handle file upload
            if ($request->hasFile('image')) {
                // Store the image
                $path = $request->file('image')->store('scan_results', 'public');

                $fullUrl = asset('storage/' . $path);

                // Get category name
                $category = Categories::find($request->category_id);
                $categoryName = $category->getTranslation('name', 'en');

                // Get image content as base64
                $image = base64_encode(file_get_contents($request->file('image')));

                // Call OpenAI API
                $openai = OpenAI::client(env('OPENAI_API_KEY'));
                $aiResponse = $openai->chat()->create([
                    'model' => env('OPENAI_MODEL'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "This is a product analysis system. Analyze the contents seen in the image.
            Dynamically change the health score according to the category specified by the user.
            If the product name or category cannot be determined, return 'null'.
            Always return the result in JSON format.
            Respond in the language specified by the user. Write all content (product name, category, ingredients, health score, explanations) in the user's specified language.
            If no valid information is found or if there is an error, include \"check\": false in the response. Otherwise, include \"check\": true.
            The JSON format should be as follows:
            {
              \"check\": true or false,
              \"product_name\": \"The name of the product in the language determined by AI (e.g., Tobacco)\",
              \"category\": \"The category of the product in the language determined by AI (e.g., Tobacco product)\",
              \"ingredients\": [\"List all ingredients in the language specified by the user\"],
              \"worst_ingredients\": [\"The worst ingredients in terms of health, in the language specified by the user\"],
              \"best_ingredients\": [\"The best ingredients in terms of health, in the language specified by the user\"],
              \"health_score\": \"Health score as a percentage, which may vary depending on the category\",
              \"detail_text\": \"Detailed information about the product in the language specified by the user (If some content is not specified, respond appropriately)\"
            }"
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => "Analyze the contents of this product and respond in the specified JSON format.
            Write the ingredients (all, worst, best), product name, product category, and detailed text in **$language**.
            Category: **$categoryName**, Language: **$language**."
                                ],
                                [
                                    'type' => 'image_url',
//                                    'image_url' => ["url" => "data:image/png;base64,$image"]
                                    'image_url' => ["url" => $fullUrl]
                                ]
                            ]
                        ]
                    ],
//                    'max_tokens' => 500,
                    'response_format' => ['type' => 'json_object'],
                ]);


                $aiResponseData = json_decode($aiResponse->choices[0]->message->content, true);

                // Create scan result record
                $scanResult = ScanResults::create([
                    'customer_id' => $user->id,
                    'category_id' => $request->category_id,
                    'image' => $path,
                    'response' => $aiResponseData,
                    'category_name_ai' => $aiResponseData['category'] ?? '',
                    'product_name_ai' => $aiResponseData['product_name'] ?? '',
                    'product_score' => isset($aiResponseData['health_score']) && $aiResponseData['health_score'] !== 'null'
                        ? (int) str_replace('%', '', $aiResponseData['health_score'])
                        : null,
                    'check' => $aiResponseData['check']
                ]);

                if($aiResponseData['check'] && $activePackage)
                {
                    $activePackage->decrement('remaining_scans');
                }

                return $this->sendResponse([
                    'scan_id' => $scanResult->id,
                    'image_path' => Storage::url($path),
                    'category_id' => $scanResult->category_id,
                    'response' => $scanResult->response
                ], 'Scan result uploaded successfully');
            }

            return $this->sendError('upload_error', 'No image file provided', 400);

        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('scan_result_error', "Scan result error - " . $e->getMessage(), 500);
        }
    }

    public function scan(Request $request, GoogleVisionService $googleVisionService): JsonResponse
    {
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

            $activePackage = $user->packages()
                ->where('remaining_scans', '>', 0)
                ->where('created_at', '>=', now()->subMonth())
                ->where('status', SubscriptionStatus::ACTIVE->value)
                ->orderByDesc('id')
                ->first();

            $allScans = $user->scan_results()
                ->count();

            if($allScans >= config('services.free_package_limit') && !$activePackage) {
                return $this->sendError('out_of_scan_limit', 'Out of scan limit');
            }

            // Handle file upload
            if ($request->hasFile('image')) {
                // Store the image
                $path = $request->file('image')->store('scan_results', 'public');

                $fullUrl = asset('storage/' . $path);

                $content = $googleVisionService->extractText($fullUrl);

                // Get category name
                $category = Categories::find($request->category_id);
                $categoryName = $category->getTranslation('name', 'en');

                // Get image content as base64
                $image = base64_encode(file_get_contents($request->file('image')));

                // Call OpenAI API
                $openai = OpenAI::client(env('OPENAI_API_KEY'));
//                $aiResponse = $openai->chat()->create([
//                    'model' => env('OPENAI_MODEL'),
//                    'messages' => [
//                        [
//                            'role' => 'system',
//                            'content' => "This is a product analysis system. Analyze the contents seen in the content.
//            Dynamically change the health score according to the category specified by the user.
//            If the product_name or category cannot be determined, return 'null'.
//            Always return the result in JSON format.
//            Respond in the language specified by the user. Write all content (product name, category, ingredients, health score, explanations) in the user's specified language.
//            If no valid information is found or if there is an error, include \"check\": false in the response. Otherwise, include \"check\": true.
//            The JSON format should be as follows:
//            {
//              \"check\": true or false,
//              \"product_name\": \"The name of the product in the language determined by AI, it is not depend on which I sending category name (e.g., Tobacco)\",
//              \"category\": \"The category of the product in the language determined by AI, it is not depend on which I sending category name (e.g., Tobacco product)\",
//              \"ingredients\": [\"List all ingredients in the language specified by the user\"],
//              \"worst_ingredients\": [\"The worst ingredients in terms of health, in the language specified by the user\"],
//              \"best_ingredients\": [\"The best ingredients in terms of health, in the language specified by the user\"],
//              \"health_score\": \"Health score as a percentage, which may vary depending on the category\",
//              \"detail_text\": \"Detailed information about the product in the language specified by the user (If some content is not specified, respond appropriately)\"
//            }"
//                        ],
//                        [
//                            'role' => 'user',
//                            'content' => [
//                                [
//                                    'type' => 'text',
//                                    'text' => "Analyze the contents of this product and respond in the specified JSON format.
//            Write the ingredients (all, worst, best), product score (according: **$categoryName**), product name, product category, and detailed text in **$language**.
//            Category: **$categoryName**, Language: **$language**."
//                                ],
//                                [
//                                    'type' => 'text',
//                                    'text' => $content
//                                ]
//                            ]
//                        ]
//                    ],
////                    'max_tokens' => 500,
//                    'response_format' => ['type' => 'json_object'],
//                ]);

                $aiResponse = $openai->chat()->create([
                    'model' => env('OPENAI_MODEL'),
                    'temperature' => 0.0,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => <<<EOT
                                You are a product analysis system.

                                Analyze the content provided by the user and return a structured JSON response.

                                Rules:
                                1. Detect the **actual product name** and **product category** from the content itself. Do NOT rely on or copy the category provided by the user. If product name or category cannot be determined, return `null` for them.
                                2. Analyze the ingredients and dynamically calculate a **health score** according to the category specified by the user (e.g., Children, Adults, Diabetics, Allergic people). For example, a product that is healthy in general may be unhealthy for children or allergic individuals.
                                3. Always respond in the **language specified by the user** (including product name, category, ingredients, score, etc.).
                                4. If valid information is found, include `"check": true`. If important data is missing or cannot be interpreted, set `"check": false`.

                                Return the result in this exact JSON format:
                                {
                                  "check": true or false,
                                  "product_name": "Detected product name in the user's language or null",
                                  "category": "Detected product category in the user's language or null",
                                  "ingredients": ["List of all ingredients in the user's language"],
                                  "worst_ingredients": ["List of worst ingredients for health, in user's language"],
                                  "best_ingredients": ["List of best ingredients for health, in user's language"],
                                  "health_score": "A percentage score based on the category specified by the user",
                                  "detail_text": "Detailed explanation in the user's language, summarizing health evaluation"
                                }
                                EOT
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => "Analyze the contents of this product and respond in the specified JSON format.
Write the ingredients (all, worst, best), health score (based on category: **$categoryName**), product name, product category, and detailed explanation in **$language**.
Category: **$categoryName**, Language: **$language**."
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $content
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
                    'ocr_text' => $content,
                    'response_time' => $responseTimeMs,
                ]);

                if($aiResponseData['check'] && $activePackage)
                {
                    $activePackage->decrement('remaining_scans');
                }

                return $this->sendResponse([
                    'scan_id' => $scanResult->id,
                    'image_path' => Storage::url($path),
                    'category_id' => $scanResult->category_id,
                    'response' => $scanResult->response
                ], 'Scan result uploaded successfully');
            }

            return $this->sendError('upload_error', 'No image file provided', 400);

        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('scan_result_error', "Scan result error - " . $e->getMessage(), 500);
        }
    }

    public function getPage($slug): JsonResponse
    {
        $locale = request()->header('Accept-Language', 'en');

        $page = Page::findBySlug($slug);

        if (!$page) {
            return $this->sendError('page_not_found', 'This page not found');
        }

        return $this->sendResponse([
            'title' => $page->getTranslation('title', $locale),
            'content' => $page->getTranslation('content', $locale),
            'language' => $locale
        ], $page->getTranslation('title', $locale) . ' page returned');
    }

    public function bugReport(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validate the request
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|min:3|max:100',
                'type' => 'required|string',
                'description' => 'required|string|min:5|max:250',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return $this->sendError('validation_error', $validator->errors(), 400);
            }

            // Handle file upload
            if ($request->hasFile('image')) {
                // Store the image
                $path = $request->file('image')->store('bug_reports', 'public');

                // Get image content as base64
                $image = base64_encode(file_get_contents($request->file('image')));

                // Create bug report record
                $bugReport = BugReports::create([
                    'customer_id' => $user->id,
                    'title' => $request->title,
                    'type' => $request->type,
                    'screenshot' => $path,
                    'description' => $request->description
                ]);

                return $this->sendResponse([
                    $bugReport
                ], 'Bug report created successfully');
            }

            return $this->sendError('upload_error', 'No image file provided', 400);

        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('bug_report_error', "Bug report error - " . $e->getMessage(), 500);
        }
    }

    public function contactUs(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validate the request
            $validator = Validator::make($request->all(), [
                'full_name' => 'required|string|min:3|max:100',
                'email' => 'required|email',
                'subject' => 'required|string|min:3|max:50',
                'message' => 'required|string|min:3|max:150',
            ]);

            if ($validator->fails()) {
                return $this->sendError('validation_error', $validator->errors(), 400);
            }


            // Create contact us record
            $bugReport = ContactUs::create([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'subject' => $request->subject,
                'message' => $request->message,
            ]);

            return $this->sendResponse([
                $bugReport
            ], 'Contact us created successfully');

        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('contact_us_error', "Contact us error - " . $e->getMessage(), 500);
        }
    }

    public function packages(Request $request): JsonResponse
    {
        try {
            $locale = $request->header('Accept-Language', 'en');

            $packages = Packages::all()->map(function ($package) use ($locale) {
                return [
                    'id' => $package->id,
                    'name' => $package->getTranslation('name',$locale) ?? 'Unknown',
                    'color' => $package->color,
                    'price' => number_format($package->price,2),
                    'scan_count' => $package->scan_count,
                    'per_scan' => $package->per_scan,
                    'saving' => $package->saving,
                    'is_popular' => $package->is_popular,
                    'product_id_for_payment' => $package->product_id_for_payment,
                    'product_id_for_purchase' => $package->product_id_for_purchase,
                ];
            });

            return $this->sendResponse($packages,'success');

        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('get_package_error', "Package error - ".$e->getMessage(), 500);
        }
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

        if ($getScanResults->isEmpty()) {
            return $this->sendError('not_found_history', 'Tarama geçmişi bulunamadı', 400);
        }

        // Favori taramaları getir
        $favoritedScanIds = $user->favoriteScanResults()
            ->pluck('scan_result_id')
            ->toArray();

        // Her tarama sonucuna is_favorite flag'ini ekle
        $resultsWithFavorites = $getScanResults->map(function ($scanResult) use ($favoritedScanIds) {
            $scanResult->is_favorite = in_array($scanResult->id, $favoritedScanIds);
            return $scanResult;
        });

        // Sayfalama bilgilerini response'a ekle
        $response = [
            'data' => $resultsWithFavorites,
            'pagination' => [
                'current_page' => $getScanResults->currentPage(),
                'last_page' => $getScanResults->lastPage(),
                'per_page' => $getScanResults->perPage(),
                'total' => $getScanResults->total(),
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

    public function getScanResult($scanId,Request $request): JsonResponse
    {
        $user = $request->user();

        $getScan = ScanResults::find($scanId);

        if($getScan->customer_id !== $user->id) {
            return $this->sendError('not_found_auth', 'Failed authorization', 401);
        }

        return $this->sendResponse($getScan, 'Scan result uploaded successfully');
    }

    public function setDefaultCategory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = validator($request->all(), [
                'category_id' => 'required|exists:categories,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('validation_error', $validator->errors()->first(), 422);
            }

            $user->default_category_id = $request->category_id;
            $user->save();

            return $this->sendResponse('success', 'Set default category successfully');
        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('set_default_category', "Set default category error - ".$e->getMessage(), 500);
        }
    }

    public function getLanguages(): JsonResponse
    {
        $languages = config('backpack.crud.locales');

        return $this->sendResponse($languages,'success');
    }

    public function setDefaultLanguage(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = validator($request->all(), [
                'language' => 'required|in:'.implode(',', array_keys(config('backpack.crud.locales'))),
            ]);

            if ($validator->fails()) {
                return $this->sendError('validation_error', $validator->errors()->first(), 422);
            }

            $user->language = $request->language;
            $user->save();

            return $this->sendResponse('success', 'Set default language successfully');
        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('set_default_category', "Set default language error - ".$e->getMessage(), 500);
        }
    }

    public function getCountries(): JsonResponse
    {
        $countries = Countries::all();

        return $this->sendResponse($countries,'success');
    }

    public function setDefaultCountry(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = validator($request->all(), [
                'country_id' => 'required|exists:countries,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('validation_error', $validator->errors()->first(), 422);
            }

            $user->country_id = $request->country_id;
            $user->save();

            return $this->sendResponse('success', 'Set default country successfully');
        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('set_default_country', "Set default country error - ".$e->getMessage(), 500);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'password' => 'required|string|min:8',
                'password_confirmation' => 'required|string|min:8|same:password'
            ]);

            if ($validator->fails()) {
                return $this->sendError('error', $validator->errors(), 400);
            }

            $existingCustomer = Customers::find($user->id);
            if (!$existingCustomer) {
                return $this->sendError('not_found_user', 'User not found', 400);
            }

            $existingCustomer->password = $request->password;
            $existingCustomer->save();

            return $this->sendResponse('success', 'Password changed', 201);

        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('change_password', "Change password error - ".$e->getMessage(), 500);
        }
    }

    public function verifySubscription(Request $request)
    {
        $log = new DebugWithTelegramService();

        try {
            $user = $request->user();

            $activePackage = $user->packages()
                ->where('created_at', '>=', now()->subMonth())
                ->where('status', SubscriptionStatus::ACTIVE->value)
                ->orderBy('id')
                ->first();

            $allScans = $user->scan_results()
                ->count();

            $permitScan = ($activePackage && $activePackage->remaining_scans > 0) || $allScans < config('services.free_package_limit');

            if($activePackage && !$permitScan) {
                $log->debug('Active package exists - ' . $user->id);
//                return $this->sendError('exist_active_package', 'Active package exists.', 400);
            }

            $validated = $request->validate([
                'product_id' => 'required|string',
                'purchase_token' => 'required|string',
                'transaction_date' => 'required|date',
            ]);

            $googleService = new GooglePayService();
            $subscriptionInfo = $googleService->verifySubscription(
                $validated['product_id'],
                $validated['purchase_token']
            );

            $now = now();
            $expiryDate = Carbon::createFromTimestamp($subscriptionInfo->expiryTimeMillis / 1000);

            if ($now->gte($expiryDate)) {
                return $this->sendError('expired', 'Subscription has already expired.', 400);
            }

            if (!isset($subscriptionInfo->paymentState) || $subscriptionInfo->paymentState != 1) {
                return $this->sendError('invalid_payment', 'Payment not completed.', 400);
            }

            $existingSubscription = Subscription::where('purchase_token', $validated['purchase_token'])->first();
            if ($existingSubscription) {
                return $this->sendResponse('already_exists', 'Subscription already exists.', 200);
            }

            $package = Packages::where('product_id_for_payment', $validated['product_id'])->firstOrFail();

            DB::transaction(function () use ($user, $validated, $subscriptionInfo, $package, $expiryDate) {
                Subscription::create([
                    'customer_id' => $user->id,
                    'product_id' => $validated['product_id'],
                    'purchase_token' => $validated['purchase_token'],
                    'start_date' => Carbon::createFromTimestamp($subscriptionInfo->startTimeMillis / 1000),
                    'expiry_date' => $expiryDate,
                    'status' => SubscriptionStatus::ACTIVE->value,
                    'auto_renewing' => $subscriptionInfo->autoRenewing ?? false,
                    'payment_details' => json_encode($subscriptionInfo),
                    'amount' => $package->price,
                ]);
            });

            return $this->sendResponse('success', 'Subscription verified successfully.', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('validation_error', $e->errors(), 422);

        } catch (\Exception $e) {
            $log->debug('Error verifying subscription: ' . $e->getMessage());
            return $this->sendError('payment_failed', 'An error occurred: ' . $e->getMessage(), 500);
        }
    }


//    public function webhookGoogleSubscription(Request $request)
//    {
//        $log = new DebugWithTelegramService();
//
//        return $this->sendResponse('success', 'Webhook processed successfully.', 200);
//
//        try {
//            $payload = json_decode(file_get_contents('php://input'), true);
//            $log->debug('Webhook Payload: ' . json_encode($payload));
//
//            $notification = $payload['subscriptionNotification'] ?? null;
//
//            if (!$notification) {
//                return $this->sendError('invalid_notification', 'No subscription notification found.', 400);
//            }
//
//            $purchaseToken = $notification['purchaseToken'] ?? null;
//            $productId = $notification['subscriptionId'] ?? null;
//            $notificationType = $notification['notificationType'] ?? null;
//
//            if (!$purchaseToken || !$productId || is_null($notificationType)) {
//                return $this->sendError('invalid_payload', 'Missing required fields.', 400);
//            }
//
//            $subscription = Subscription::where('purchase_token', $purchaseToken)->first();
//            if (!$subscription) {
//                return $this->sendError('subscription_not_found', 'Subscription not found.', 404);
//            }
//
//            $notificationEnum = GoogleNotificationType::tryFrom($notificationType);
//            if (!$notificationEnum) {
//                return $this->sendError('invalid_notification_type', 'Invalid notification type.', 400);
//            }
//
//            $newStatus = $notificationEnum->toStatus();
//
//            if ($newStatus != SubscriptionStatus::UNCHANGED->value) {
//                $subscription->update(['status' => $newStatus->value]);
//            }
//
//            if ($newStatus->value == SubscriptionStatus::ACTIVE->value) {
//                $log->debug('active');
//                $this->activateCustomerPackage($subscription);
//            } elseif (in_array($newStatus->value, [SubscriptionStatus::PAUSED->value, SubscriptionStatus::CANCELED->value, SubscriptionStatus::EXPIRED->value])) {
//                $log->debug('inactive');
//                $this->deactivateCustomerPackage($subscription, $newStatus->value);
//            }
//
//            $log->debug('Subscription status updated to: ' . $newStatus->value);
//
//            return $this->sendResponse('success', 'Webhook processed successfully.', 200);
//
//        } catch (\Exception $e) {
//            $log->debug('Error processing webhook: ' . $e->getMessage());
//            return $this->sendError('webhook_error', 'Error: ' . $e->getMessage(), 500);
//        }
//    }

    public function webhookGoogleSubscription(Request $request)
    {
        $log = new DebugWithTelegramService();

        try {
            $payload = json_decode(file_get_contents('php://input'), true);
            $log->debug('Webhook Payload: ' . json_encode($payload));

            $notification = $payload['voidedPurchaseNotification'] ?? null;

//            return $this->sendResponse('success', 'Webhook processed successfully.', 200);

            if (!$notification) {
                return $this->sendError('invalid_notification', 'No subscription notification found.', 400);
            }

            $purchaseToken = $notification['purchaseToken'] ?? null;
            $refundType = $notification['refundType'];

            if (!$purchaseToken) {
                return $this->sendError('invalid_payload', 'Missing required fields.', 400);
            }

            $subscription = Subscription::where('purchase_token', $purchaseToken)->first();

            $customerPackage = CustomerPackages::where('subscription_id', $subscription->id)->first();

            if (!$customerPackage || !$subscription) {
                $log->debug('not_found_sub');
                return $this->sendError('customer_package_or_subscription_not_found', 'Subscription or customer package not found.');
            }

            $customerPackage->status = $refundType == 1 ? 'refund' : 'unknown';
            $customerPackage->save();

            $purchase = $subscription->replicate();
            $purchase->payment_details = json_encode($payload);
            $purchase->status = $refundType == 1 ? 'refund' : 'unknown';
            $purchase->parent_id = $subscription->id;
            $purchase->save();

            $log->debug('Subscription status updated to: ' . ($refundType == 1 ? 'refund' : 'unknown'));

            return $this->sendResponse('success', 'Webhook processed successfully.', 200);

        } catch (\Exception $e) {
            $log->debug('Error processing webhook: ' . $e->getMessage());
            return $this->sendError('webhook_error', 'Error: ' . $e->getMessage(), 500);
        }
    }

    private function activateCustomerPackage(Subscription $subscription)
    {
        $package = Packages::where('product_id_for_payment', $subscription->product_id)->firstOrFail();

        $customerPackage = CustomerPackages::where('subscription_id', $subscription->id)->first();

        if($customerPackage) {
            CustomerPackages::where('subscription_id', $subscription->id)->where('status',SubscriptionStatus::ACTIVE->value)
                ->update(['status' => SubscriptionStatus::INACTIVE->value]);
        }

        CustomerPackages::create([
            'customer_id' => $subscription->customer_id,
            'package_id' => $package->id,
            'remaining_scans' => $package->scan_count,
            'subscription_id' => $subscription->id,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);
    }

    private function deactivateCustomerPackage(Subscription $subscription, string $newStatus)
    {
        $customerPackage = CustomerPackages::where('subscription_id', $subscription->id)->first();
        if ($customerPackage) {
            CustomerPackages::where('subscription_id', $subscription->id)->where('status',SubscriptionStatus::ACTIVE->value)
                ->update(['status' => $newStatus]);
        }
    }


    public function checkPayment()
    {
        try {
            $jsonPath = storage_path('app/vital-scan-vscan-908399013c6f.json');

            $client = new GoogleClient();
            $client->setAuthConfig($jsonPath);
            $client->addScope(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);
            $client->setApplicationName('VScan');
//            $client->setAccessType('offline');

            $androidPublisher = new Google_Service_AndroidPublisher($client);

//            $subscription = $androidPublisher->purchases_subscriptions->get(
//                'com.healthyproduct.app',
//                'basic_package',
//                'nlmilhkmcklkojapgbnicfjg.AO-J1OxyaK254_RIUVqP0pkBLkGp2B9PKwSPdsKKm4p_S7E4WjQkotxsQ3N1z6KgTB3gdwM9qHF4B7YzfZ4Eiga-ytZbZCOgRGXNqa8g8C8EmzbOcwLhFG0'
//            );

            $subscription = $androidPublisher->purchases_products->get(
                'com.healthyproduct.app',
                'premium_package_1500',
                'mpedaloengdmahbfncmknbjn.AO-J1OxS21gq-R8xvVHxos-H-cWDP6GYK_INQfKKRgF_rBor3MFElWQUphVAdVQ1EZbvnwaY98fk4HP26vXTwk7qHQJcXK-8ZnfVuyjlg_RNO60_6G0HONU'
            );

            dd($subscription);


            return $this->sendError('system_error','Webhook processed successfully', 200);

        } catch (\Exception $e) {
            return $this->sendError('error_processing_webhook','Error processing webhook: ' . $e->getMessage(), 500);
        }
    }

    public function storeDeviceToken(Request $request)
    {
        $log = new DebugWithTelegramService();
        $log->debug($request->all());
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'device_token' => 'required|string',
            'device_type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('error', $validator->errors(), 422);
        }

        try {
            // Kullanıcının mevcut token'ını kontrol et
            $existingToken = DeviceToken::where('customer_id', $user->id)
                ->where('device_token', $request->device_token)
                ->where('device_type', $request->device_type)
                ->first();

            if (!$existingToken) {
                // Yeni token kaydet
                DeviceToken::create([
                    'customer_id' => $user->id,
                    'device_token' => $request->device_token,
                    'device_type' => $request->device_type,
                ]);
            }

            return $this->sendResponse('success','Device token saved successfully');

        } catch (\Exception $e) {
            return $this->sendError('failed_to_save_device_token','Failed to save device token: ' . $e->getMessage(), 500);
        }
    }

    public function checkAppVersion(Request $request)
    {
        // Client'dan gelen version ve platform bilgisi
        $currentVersion = $request->header('X-App-Version'); // Örn: "1.0.0"
        $platform = $request->header('X-Platform'); // "ios" veya "android"

        // Veritabanında veya config'de_DE tutulan en son versiyon bilgileri
        $latestVersions = [
            'ios' => [
                'version' => '1.0.0',
                'force_update' => true,
                'store_url' => 'https://apps.apple.com/app/your-app-id',
                'description' => 'Yeni özellikler ve iyileştirmeler için lütfen uygulamayı güncelleyin.',
            ],
            'android' => [
                'version' => '1.1.1',
                'force_update' => true,
                'store_url' => 'https://play.google.com/store/apps/details?id=com.healthyproduct.app',
                'description' => 'Yeni özellikler ve iyileştirmeler için lütfen uygulamayı güncelleyin.',
            ]
        ];

        if (!$currentVersion || !$platform) {
            return $this->sendError('version_or_platform_not_provided','Version or platform not provided', 400);
        }

        $latestVersion = $latestVersions[$platform] ?? null;

        if (!$latestVersion) {
            return $this->sendError('invalid_platform','Invalid platform', 400);
        }

        // Version karşılaştırması
        $needsUpdate = version_compare($currentVersion, $latestVersion['version'], '<');

        $data = [
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion['version'],
            'needs_update' => $needsUpdate,
            'force_update' => $needsUpdate && $latestVersion['force_update'],
            'store_url' => $latestVersion['store_url'],
            'description' => $latestVersion['description'],
        ];

        return $this->sendResponse($data, 'success');
    }

    public function getPushNotifications(Request $request)
    {
        $user = $request->user();

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $query = PushNotification::where('customer_id', $user->id)->orderBy('id', 'desc');

        $paginatedResults = $query->paginate($perPage, ['*'], 'page', $page);

        $response = [
            'data' => $paginatedResults->items(),
            'pagination' => [
                'current_page' => $paginatedResults->currentPage(),
                'last_page' => $paginatedResults->lastPage(),
                'per_page' => $paginatedResults->perPage(),
                'total' => $paginatedResults->total(),
            ]
        ];

        return $this->sendResponse($response, 'success');
    }

    public function getPushNotification($notificationId, Request $request)
    {
        $user = $request->user();

        $getPushNotification = PushNotification::find($notificationId);

        if(!$getPushNotification || $getPushNotification->customer_id !== $user->id) {
            return $this->sendError('not_found', 'Not found notification', 404);
        }

        return $this->sendResponse($getPushNotification, 'success');
    }

    public function updateStatusPushNotification(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:push_notifications,id',
            'status' => 'required|string|in:read,unread'
        ]);

        if ($validator->fails()) {
            return $this->sendError('validation_error', $validator->errors(), 400);
        }

        $getPushNotification = PushNotification::find($request->id);

        if(!$getPushNotification || $getPushNotification->customer_id !== $user->id) {
            return $this->sendError('not_found', 'Not found notification', 404);
        }

        $getPushNotification->status = $request->status;
        $getPushNotification->save();

        return $this->sendResponse($getPushNotification, 'Notification status updated successfully.');
    }

    public function getUnreadNotificationCount(Request $request)
    {
        $user = $request->user();

        $unreadCount = PushNotification::where('customer_id', $user->id)
            ->where('status', 'unread')
            ->count();

        return $this->sendResponse([
            'count' => $unreadCount
        ], 'success');
    }

    public function verifyPurchase(Request $request)
    {
        $log = new DebugWithTelegramService();

        try {
            $user = $request->user();

            $validated = $request->validate([
                'product_id' => 'required|string',
                'purchase_token' => 'required|string',
                'platform' => 'required|in:ios,android',
            ]);

            // Platform'a göre doğrulama servisini seç
//            $verificationService = $validated['platform'] === 'ios'
//                ? new AppStoreVerificationService()
//                : new GooglePlayVerificationService();

            $existingPurchase = Subscription::where('purchase_token', $validated['purchase_token'])->first();
            if ($existingPurchase) {
                return $this->sendResponse('already_exists', 'Purchase already exists.', 200);
            }

            $googlePlayService = new GooglePayVerificationService();

            $purchaseInfo = $googlePlayService->verifyPurchase(
                $validated['product_id'],
                $validated['purchase_token']
            );

            if ($purchaseInfo->purchaseState !== 0) { // 0 = purchased
                return $this->sendError('invalid_purchase', 'Invalid purchase state.', 400);
            }

            $activePackage = $user->packages()
                ->where('created_at', '>=', now()->subMonth())
                ->where('status', SubscriptionStatus::ACTIVE->value)
                ->orderByDesc('id')
                ->first();

            $allScans = $user->scan_results()
                ->count();

            $permitScan = ($activePackage && $activePackage->remaining_scans > 0) || $allScans < config('services.free_package_limit');

            if($activePackage && !$permitScan) {
                $log->debug('Active package exists - ' . $user->id . " - ". $validated['purchase_token']);
                return $this->sendError('exist_active_package', 'Active package exists.', 400);
            }

            // Satın alma doğrulaması
//            $purchaseInfo = $verificationService->verifyPurchase(
//                $validated['product_id'],
//                $validated['purchase_token'],
//                $validated['receipt_data']
//            );

            // Daha önce satın alınmış mı kontrol et
            $product = Packages::where('product_id_for_purchase', $validated['product_id'])->firstOrFail();

            DB::transaction(function () use ($user, $validated, $purchaseInfo, $product) {
                // Satın almayı kaydet
                $purchase = Subscription::create([
                    'customer_id' => $user->id,
                    'product_id' => $product->id,
                    'purchase_token' => $validated['purchase_token'],
                    'platform' => $validated['platform'],
                    'status' => SubscriptionStatus::ACTIVE->value,
                    'transaction_id' => $purchaseInfo->orderId,
                    'payment_details' => json_encode($purchaseInfo),
                    'amount' => $product->price
                ]);

                CustomerPackages::create([
                    'customer_id' => $user->id,
                    'package_id' => $product->id,
                    'remaining_scans' => $product->scan_count,
                    'subscription_id' => $purchase->id,
                    'status' => SubscriptionStatus::ACTIVE->value,
                ]);

                // Ürün tipine göre işlem yap
                    // Kullanıcının scan sayısını güncelle
//                $user->remaining_scans += $product->scan_count;
//                $user->save();
            });

            return $this->sendResponse('success', 'Purchase verified successfully.', 200);

        } catch (\Exception $e) {
            $log->debug('Error verifying purchase2: ' . $e->getMessage());
            return $this->sendError('purchase_failed', 'An error occurred: ' . $e->getMessage(), 500);
        }
    }

    public function webhookGooglePlay(Request $request)
    {
        $log = new DebugWithTelegramService();

        try {
            $payload = json_decode(file_get_contents('php://input'), true);
            $log->debug('Webhook Payload: ' . json_encode($payload));

            // Google Play'den gelen imzayı doğrula
            $signature = $request->header('X-Google-Play-Signature');
            if (!$this->verifyGooglePlaySignature($signature, $request->getContent())) {
                return $this->sendError('invalid_signature', 'Invalid signature.', 401);
            }

            $notification = $payload['subscriptionNotification'] ?? null;
            if (!$notification) {
                return $this->sendError('invalid_notification', 'No notification found.', 400);
            }

            $purchaseToken = $notification['purchaseToken'] ?? null;
            $productId = $notification['subscriptionId'] ?? null;
            $notificationType = $notification['notificationType'] ?? null;

            if (!$purchaseToken || !$productId || is_null($notificationType)) {
                return $this->sendError('invalid_payload', 'Missing required fields.', 400);
            }

            // Satın almayı bul
            $purchase = UserPurchase::where('purchase_token', $purchaseToken)->first();
            if (!$purchase) {
                return $this->sendError('purchase_not_found', 'Purchase not found.', 404);
            }

            // Bildirim tipine göre işlem yap
            switch ($notificationType) {
                case 1: // PURCHASED
                    $purchase->update(['status' => 'completed']);
                    break;
                case 2: // CANCELED
                    $purchase->update(['status' => 'canceled']);
                    // Eğer scan_pack ise ve iade edildiyse, scan sayısını düş
                    if ($purchase->product->type === 'scan_pack') {
                        $user = $purchase->user;
                        $user->remaining_scans -= $purchase->product->scan_count;
                        $user->save();
                    }
                    break;
                case 3: // REFUNDED
                    $purchase->update(['status' => 'refunded']);
                    // Scan sayısını düş
                    if ($purchase->product->type === 'scan_pack') {
                        $user = $purchase->user;
                        $user->remaining_scans -= $purchase->product->scan_count;
                        $user->save();
                    }
                    break;
            }

            // Log oluştur
            PurchaseLog::create([
                'user_id' => $purchase->user_id,
                'product_id' => $purchase->product_id,
                'event_type' => 'webhook_notification',
                'payload' => json_encode($payload)
            ]);

            return $this->sendResponse('success', 'Webhook processed successfully.', 200);

        } catch (\Exception $e) {
            $log->debug('Error processing webhook: ' . $e->getMessage());
            return $this->sendError('webhook_error', 'Error: ' . $e->getMessage(), 500);
        }
    }

    public function webhookAppStore(Request $request)
    {
        $log = new DebugWithTelegramService();

        try {
            $payload = json_decode(file_get_contents('php://input'), true);
            $log->debug('Webhook Payload: ' . json_encode($payload));

            // App Store'dan gelen imzayı doğrula
            $signature = $request->header('X-Apple-Signature');
            if (!$this->verifyAppStoreSignature($signature, $request->getContent())) {
                return $this->sendError('invalid_signature', 'Invalid signature.', 401);
            }

            $notificationType = $payload['notificationType'] ?? null;
            $signedPayload = $payload['data']['signedPayload'] ?? null;

            if (!$notificationType || !$signedPayload) {
                return $this->sendError('invalid_payload', 'Missing required fields.', 400);
            }

            // Payload'ı decode et
            $decodedPayload = $this->decodeAppStorePayload($signedPayload);

            // Satın almayı bul
            $purchase = UserPurchase::where('transaction_id', $decodedPayload['transactionId'])->first();
            if (!$purchase) {
                return $this->sendError('purchase_not_found', 'Purchase not found.', 404);
            }

            // Bildirim tipine göre işlem yap
            switch ($notificationType) {
                case 'CONSUMPTION_REQUEST':
                    $purchase->update(['status' => 'completed']);
                    break;
                case 'REFUND':
                    $purchase->update(['status' => 'refunded']);
                    // Scan sayısını düş
                    if ($purchase->product->type === 'scan_pack') {
                        $user = $purchase->user;
                        $user->remaining_scans -= $purchase->product->scan_count;
                        $user->save();
                    }
                    break;
            }

            // Log oluştur
            PurchaseLog::create([
                'user_id' => $purchase->user_id,
                'product_id' => $purchase->product_id,
                'event_type' => 'webhook_notification',
                'payload' => json_encode($payload)
            ]);

            return $this->sendResponse('success', 'Webhook processed successfully.', 200);

        } catch (\Exception $e) {
            $log->debug('Error processing webhook: ' . $e->getMessage());
            return $this->sendError('webhook_error', 'Error: ' . $e->getMessage(), 500);
        }
    }

    public function getOrderHistory(Request $request)
    {
        $user = $request->user();

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $query = CustomerPackages::with(['subscription','package']) // İlişkili subscription'u yükle
        ->where('customer_id', $user->id)
            ->orderBy('id', 'desc');

        $paginatedResults = $query->paginate($perPage, ['*'], 'page', $page);

        $response = [
            'data' => $paginatedResults->items(),
            'pagination' => [
                'current_page' => $paginatedResults->currentPage(),
                'last_page' => $paginatedResults->lastPage(),
                'per_page' => $paginatedResults->perPage(),
                'total' => $paginatedResults->total(),
            ]
        ];

        return $this->sendResponse($response, 'success');
    }

}
