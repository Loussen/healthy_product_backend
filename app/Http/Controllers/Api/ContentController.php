<?php

namespace App\Http\Controllers\Api;

use App\Models\Categories;
use App\Models\Countries;
use App\Models\Packages;
use App\Models\Page;
use App\Services\DebugWithTelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends BaseController
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
                    'description' => strip_tags($category->getTranslation('description',$locale)) ?: '',
                ];
            });

            return $this->sendResponse($categories,'success');

        } catch (\Throwable $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('get_category_error', "Category error - ".$e->getMessage(), 500);
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
                    'product_id_for_purchase_apple' => $package->product_id_for_purchase_apple,
                ];
            });

            return $this->sendResponse($packages,'success');

        } catch (\Throwable $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('get_package_error', "Package error - ".$e->getMessage(), 500);
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

    public function getLanguages(): JsonResponse
    {
        $languages = config('backpack.crud.locales');

        return $this->sendResponse($languages,'success');
    }

    public function getCountries(): JsonResponse
    {
        $countries = Countries::all();

        return $this->sendResponse($countries,'success');
    }

    public function checkAppVersion(Request $request)
    {
        // Client'dan gelen version ve platform bilgisi
        $currentVersion = $request->header('X-App-Version'); // Örn: "1.0.0"
        $platform = $request->header('X-Platform'); // "ios" veya "android"

        $latestVersions = [
            'ios' => [
                'version' => config('services.app_version.ios', '1.0.0'),
                'force_update' => false,
                'store_url' => config('services.apple.app_store_url'),
                'description' => 'Please update the app for new features and improvements.',
            ],
            'android' => [
                'version' => config('services.app_version.android', '1.0.0'),
                'force_update' => false,
                'store_url' => config('services.play_store_url'),
                'description' => 'Please update the app for new features and improvements.',
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
}
