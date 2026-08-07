<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared hosting rewrites into public/, so browsers can hit both:
 *   https://example.com/blog
 *   https://example.com/public/blog
 *
 * Do not use redirect()->to('/path') here: when REQUEST_URI starts with
 * /public, Laravel detects application base path "/public" and rebuilds
 * the same /public/... URL (301 loop to self). Always use APP_URL + path.
 */
final class StripPublicUrlPrefix
{
    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->server->get('REQUEST_URI', '');
        $path = parse_url($uri, PHP_URL_PATH) ?? '';

        if ($path === '' || ! preg_match('#^/public(?:/|$)#i', $path)) {
            return $next($request);
        }

        $stripped = preg_replace('#^/public#i', '', $path) ?: '/';
        if ($stripped === '' || $stripped[0] !== '/') {
            $stripped = '/'.$stripped;
        }

        $root = rtrim((string) config('app.url'), '/');
        // Guard: APP_URL itself must not end with /public
        $root = (string) preg_replace('#/public$#i', '', $root);

        $target = $root.$stripped;
        $query = $request->getQueryString();
        if ($query !== null && $query !== '') {
            $target .= '?'.$query;
        }

        return redirect()->away($target, 301);
    }
}
