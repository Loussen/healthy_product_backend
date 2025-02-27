<?php

namespace App\Http\Controllers\Api;

use App\Models\Categories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MainController extends BaseController
{
    public function categories(Request $request): JsonResponse
    {
        try {
            $locale = $request->header('Accept-Language', 'en');

            $categories = Categories::all()->map(function ($category) use ($locale) {
                return [
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
}
