<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class Locale
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $lang = ($request->route('locale') && array_key_exists($request->route('locale'), Config::get('services.locales'))) ? $request->route('locale') : Config::get('services.default_locale');

        App::setLocale($lang);

        return $next($request);
    }
}

