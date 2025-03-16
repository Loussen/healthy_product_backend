<?php

namespace App\Http\Controllers\Api;

use App\Models\BugReports;
use App\Models\Categories;
use App\Models\ContactUs;
use App\Models\Customers;
use App\Models\Packages;
use App\Models\Page;
use App\Models\ScanResults;
use App\Services\DebugWithTelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use OpenAI;

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
                    'color' => hexToMaterialColor($category->color ?? '#9E9E9E')
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
        $user = $request->user();
        $getCustomer = Customers::with('scan_results')->find($user->id);

        if (is_null($getCustomer)) {
            return $this->sendError('customer_not_found', 'Customer not found');
        }

        $highestScoreScan = $getCustomer->scan_results()->orderByDesc('product_score')->first();
        $lowestScoreScan = $getCustomer->scan_results()
            ->where('product_score', '>', 0)
            ->orderBy('product_score')
            ->first();

        $thisMonthScans = $getCustomer->scan_results()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $todayScans = $getCustomer->scan_results()
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $allScans = $getCustomer->scan_results()
            ->count();

        $getCustomer->makeHidden(['scan_results']);

        $activePackage = $user->packages()
            ->where('created_at', '>=', now()->subMonth())
            ->orderBy('id')
            ->first();

        if($activePackage)
            $activePackage['package'] = $activePackage->package;

        return $this->sendResponse(
            array_merge($getCustomer->toArray(), [
                'highest_score_scan' => $highestScoreScan,
                'lowest_score_scan' => $lowestScoreScan,
                'monthly_scans' => $thisMonthScans,
                'daily_scans' => $todayScans,
                'free_scan_limit' => config('services.free_package_limit'),
                'usage_limit' => $allScans > config('services.free_package_limit') ? config('services.free_package_limit') : $allScans,
                'active_package' => $activePackage,
                'permit_scan' => ($activePackage && $activePackage->remaining_scans > 0) || $allScans < config('services.free_package_limit')
            ]),
            'success'
        );
    }


    public function scan(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validate the request
            $validator = Validator::make($request->all(), [
                'category_id' => 'required|numeric|exists:categories,id',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $language = request()->input('language', 'tr');

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
                            'content' => "Bu bir ürün analiz sistemidir. Resimde görülen içerikleri analiz et.
                            Kullanıcının belirttiği kategoriye göre sağlık puanını dinamik olarak değiştir.
                            Eğer ürün adı veya kategori belirlenemiyorsa, 'Bilinmiyor' veya 'Belirtilmemiş Ürün' yazmak yerine 'null' döndür.
                            Sonucu kesinlikle JSON formatında döndür:
                            {
                              \"product_name\": \"Ürünün adı AI tarafından belirlenecek\",
                              \"category\": \"Ürünün AI tarafından belirlenen kategorisi\",
                              \"ingredients\": [\"Liste olarak tüm içerikler\"],
                              \"worst_ingredients\": [\"Sağlık açısından en kötü içerikler\"],
                              \"best_ingredients\": [\"Sağlık açısından en iyi içerikler\"],
                              \"health_score\": \"Yüzde olarak sağlık puanı, kategoriye göre değişebilir\",
                              \"detail_text\": \"Ürün hakkında detaylı bilgi\"
                            }"
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => "Bu ürünün içeriklerini analiz et ve belirtilen JSON formatında cevap ver.
                                    Kategori: $categoryName, Dil: $language"
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => ["url" => "data:image/png;base64,$image"]
                                ]
                            ]
                        ]
                    ],
                    'max_tokens' => 1000,
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
                    'product_score' => $aiResponseData['health_score'] ? str_replace('%','',$aiResponseData['health_score']) ?? '' : 0,
                ]);

                $activePackage->decrement('remaining_scans');

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
                ];
            });

            return $this->sendResponse($packages,'success');

        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('get_package_error', "Package error - ".$e->getMessage(), 500);
        }
    }
}
