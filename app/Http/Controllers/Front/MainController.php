<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Carbon\Carbon;
use Illuminate\View\View;

class MainController extends Controller
{
    /**
     * Display the home page.
     *
     * @return View
     */
    public function dashboard()
    {
        return view('index');
    }

    public function page($locale = null, $slug = null)
    {
        if (is_null($slug)) {
            abort(404);
        }

        $page = Page::where('slug', $slug)->first();

        if (!$page || $page->isBlogArticle()) {
            abort(404);
        }

        Carbon::setLocale(app()->getLocale());

        $icon = '🛡️';

        if($page->slug == 'terms-conditions')
            $icon = '📝';
        elseif($page->slug == 'about-us')
            $icon = 'ℹ️';
        elseif($page->slug == 'sources')
            $icon = '📄';
        else
            $icon = 'ℹ️';

        return view('page', ['page' => $page, 'icon' => $icon]);
    }
}
