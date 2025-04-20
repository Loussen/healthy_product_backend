<?php

namespace App\Http\Controllers\Api;

use App\Models\BugReports;
use App\Models\Categories;
use App\Models\ContactUs;
use App\Models\Countries;
use App\Models\CustomerPackages;
use App\Models\Customers;
use App\Models\Packages;
use App\Models\Page;
use App\Models\ScanResults;
use App\Models\Subscription;
use App\Services\DebugWithTelegramService;
use App\Services\GoogleVisionService;
use Carbon\Carbon;
//use Google\Cloud\AIPlatform\V1\Client\PredictionServiceClient;
//use Google\Cloud\AIPlatform\V1\PredictRequest;
//use Google\Protobuf\Struct;
//use Google\Protobuf\Value;
use Google\Service\AndroidPublisher;
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
            ->orderBy('id')
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

                $content = $googleVisionService->extractText($fullUrl);

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
                            'content' => "This is a product analysis system. Analyze the contents seen in the content.
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
                                    'type' => 'text',
                                    'text' => $content
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
//                    'product_name_ai' => $aiResponseData['product_name'] && $aiResponseData['product_name'] != 'null' ? $aiResponseData['product_name'] : '',
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
        try {
            $user = $request->user();

            // Request validation
            $validated = $request->validate([
                'product_id' => 'required|string',
                'purchase_token' => 'required|string',
                'transaction_date' => 'required|date',
            ]);

            $jsonPath = storage_path('app/vital-scan-vscan-908399013c6f.json');

            if (!file_exists($jsonPath)) {
                $log = new DebugWithTelegramService();
                $log->debug('Json file not found');
                throw new \Exception('Service account JSON file not found');
            }

            // Google Client setup
            $client = new GoogleClient();
            $client->setAuthConfig($jsonPath); // Google Play Console'dan indirdiğiniz service account json
            $client->addScope('https://www.googleapis.com/auth/androidpublisher');
            $client->setApplicationName('VScan');
            $client->setAccessType('offline');

            // Android Publisher service
            $androidPublisher = new AndroidPublisher($client);

            // Subscription doğrulama
            $result = $androidPublisher->purchases_subscriptions->get(
                'com.healthyproduct.app', // Android uygulama package name
                $validated['product_id'],
                $validated['purchase_token']
            );

            $now = Carbon::now();
            $expiry = Carbon::createFromTimestamp($result->expiryTimeMillis / 1000);
            $start = Carbon::createFromTimestamp($result->startTimeMillis / 1000);

            if ($now->greaterThanOrEqualTo($expiry)) {
                return $this->sendError('expired', 'Subscription expired', 400);
            }

            if (isset($result->cancelReason)) {
                return $this->sendError('cancelled', 'Subscription was cancelled', 400);
            }

            // Subscription durumunu kontrol et
            if (isset($result->paymentState) && $result->paymentState == 1) {
                DB::beginTransaction();

                $package = Packages::where('product_id_for_payment', $validated['product_id'])->first();
                if (!$package) {
                    $log = new DebugWithTelegramService();
                    $log->debug('Package not found for product ID: ' . $validated['product_id']);
                    throw new \Exception('Package not found for product ID: ' . $validated['product_id']);
                }

                // Subscription'ı veritabanına kaydet
                $subscription = Subscription::create(
                    [
                        'customer_id' => $user->id,
                        'product_id' => $validated['product_id'],
                        'purchase_token' => $validated['purchase_token'],
                        'start_date' => Carbon::createFromTimestamp($result->startTimeMillis / 1000),
                        'expiry_date' => Carbon::createFromTimestamp($result->expiryTimeMillis / 1000),
                        'status' => 'active',
                        'auto_renewing' => $result->autoRenewing,
                        'payment_details' => $result,
                        'amount' => $package->price,
                    ]
                );

                $customerPackage = CustomerPackages::create([
                    'customer_id' => $user->id,
                    'package_id' => $package->id,
                    'remaining_scans' => $package->scan_count,
                    'subscription_id' => $subscription->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                DB::commit();
                return $this->sendResponse('success', 'Subscription verified and saved', 201);
            }

            return $this->sendError('invalid_subscription', 'Invalid subscription payment state', 400);

        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('payment_failed', "Payment error - ".$e->getMessage(), 500);
        }
    }

    public function webhookGoogleSubscription(Request $request)
    {
        $log = new DebugWithTelegramService();
        $rawBody = $request->getContent();
        $log->debug('Google Webhook Raw: ' . $rawBody);

        $data = json_decode($rawBody, true);

        if (!isset($data['message']['data'])) {
            $log->debug('No message data found');
            return response()->json(['status' => 'error', 'message' => 'No message data'], 200); // 200 dön ki tekrar etmesin
        }

        $decodedMessage = base64_decode($data['message']['data']);
        $decodedData = json_decode($decodedMessage, true);

        $log->debug('Decoded Data: ' . json_encode($decodedData));

        return response()->json(['status' => 'success'], 200); // Google memnun kalsın :)
    }

}
