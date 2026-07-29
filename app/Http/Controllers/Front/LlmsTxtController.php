<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Response;

class LlmsTxtController extends Controller
{
    public function __invoke(): Response
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $defaultLocale = config('services.default_locale', 'en');
        $locales = config('services.locales', []);

        $lines = [
            '# Vital Scan',
            '',
            '> Your vital scan compass — scan food product labels, analyze ingredients, and make healthier shopping decisions.',
            '',
            'Vital Scan is a free mobile health app (iOS and Android) that uses AI to scan product ingredient labels, calculate health scores, detect allergens, and provide personalized nutrition recommendations.',
            '',
            '## App Downloads',
            '',
            '- iOS: ' . config('services.apple.app_store_url', 'https://apps.apple.com/us/app/id6755874667'),
            '- Android: ' . config('services.play_store_url', 'https://play.google.com/store/apps/details?id=com.healthyproduct.app'),
            '',
            '## Contact',
            '',
            '- Email: info@vitalscan.app',
            '- Website: ' . $baseUrl,
            '',
            '## Supported Languages',
            '',
        ];

        foreach ($locales as $code => $name) {
            $lines[] = "- {$name}: {$baseUrl}/{$code}";
        }

        $lines = array_merge($lines, [
            '',
            '## Key Pages',
            '',
            '- Home: ' . $baseUrl . '/' . $defaultLocale,
            '- Blog: ' . $baseUrl . '/' . $defaultLocale . '/blog',
            '- About Us: ' . $baseUrl . '/' . $defaultLocale . '/about-us',
            '- Privacy Policy: ' . $baseUrl . '/' . $defaultLocale . '/privacy-policy',
            '- Terms & Conditions: ' . $baseUrl . '/' . $defaultLocale . '/terms-conditions',
            '- Sources: ' . $baseUrl . '/' . $defaultLocale . '/sources',
            '',
            '## Blog Articles',
            '',
        ]);

        $articles = $this->getBlogArticles();

        if ($articles->isEmpty()) {
            $lines[] = '- (See blog index: ' . $baseUrl . '/' . $defaultLocale . '/blog)';
        } else {
            foreach ($articles as $article) {
                $title = strip_tags($article->getTranslation('title', $defaultLocale) ?: $article->title);
                $lines[] = '- ' . $title . ': ' . $baseUrl . '/' . $defaultLocale . '/blog/' . $article->slug;
            }
        }

        $lines = array_merge($lines, [
            '',
            '## Features',
            '',
            '- Instant product label scanning via camera',
            '- AI-powered ingredient analysis and health score',
            '- Allergen detection based on user profile',
            '- Harmful additive and E-number flagging',
            '- Scan history and personalized recommendations',
            '',
            '## Optional',
            '',
            '- Sitemap: ' . $baseUrl . '/sitemap.xml',
            '- Robots: ' . $baseUrl . '/robots.txt',
        ]);

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function getBlogArticles()
    {
        try {
            return Page::blogArticles()->orderByDesc('updated_at')->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}
