<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Carbon\Carbon;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $articles = Page::blogArticles()
            ->orderByDesc('updated_at')
            ->get();

        return view('blog.index', compact('articles'));
    }

    public function show(?string $locale, string $slug): View
    {
        $article = Page::blogArticles()
            ->where('slug', $slug)
            ->firstOrFail();

        Carbon::setLocale(app()->getLocale());

        $related = Page::blogArticles()
            ->where('id', '!=', $article->id)
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get();

        return view('blog.article', compact('article', 'related'));
    }
}
