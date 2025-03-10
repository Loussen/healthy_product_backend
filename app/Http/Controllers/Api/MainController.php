<?php

namespace App\Http\Controllers\Api;

use App\Models\Categories;
use App\Models\Customers;
use App\Models\Page;
use App\Models\ScanResults;
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
            return $this->sendError('get_category_error', "Category error - ".$e->getMessage(), 500);
        }
    }

    public function customer(Request $request): JsonResponse
    {
        $user = $request->user();
        $getCustomer = Customers::find($user->id);

        if (is_null($getCustomer)) {
            return $this->sendError('customer_not_found','Customer not found');
        }

        return $this->sendResponse($getCustomer,'success');
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
                            Sonucu kesinlikle JSON formatında döndür:
                            {
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
                                    'text' => "Bu ürünün içeriklerini analiz et ve belirtilen JSON formatında cevap ver. Kategori: $categoryName, Dil: $language"
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
                    'response' => $aiResponseData
                ]);

                return $this->sendResponse([
                    'scan_id' => $scanResult->id,
                    'image_path' => Storage::url($path),
                    'category_id' => $scanResult->category_id,
                    'response' => $scanResult->response
                ], 'Scan result uploaded successfully');
            }

            return $this->sendError('upload_error', 'No image file provided', 400);

        } catch (\Exception $e) {
            return $this->sendError('upload_error', "Upload error - " . $e->getMessage(), 500);
        }
    }

    public function getPage($slug): JsonResponse
    {
        $page = Page::findBySlug($slug);

        if (!$page)
        {
            return $this->sendError('page_not_found', 'This page not found', 404);
        }

        return $this->sendResponse([
            'title' => $page->title,
            'content' => $page->content
        ], $page->title . ' page returned');
    }
}
