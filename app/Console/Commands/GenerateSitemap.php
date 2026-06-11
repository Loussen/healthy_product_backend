<?php

namespace App\Console\Commands;

use App\Models\Page;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the sitemap.';

    public function handle()
    {
        $sitemap = Sitemap::create();
        $locales = array_keys(config('services.locales', []));

        $sitemap->add(Url::create('/')
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            ->setPriority(1.0));

        foreach ($locales as $locale) {
            $sitemap->add(Url::create("/{$locale}")
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(1.0));

            $sitemap->add(Url::create("/{$locale}/blog")
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                ->setPriority(0.9));
        }

        $pages = Page::all();

        foreach ($pages as $page) {
            foreach ($locales as $locale) {
                $priority = 0.7;
                $frequency = Url::CHANGE_FREQUENCY_MONTHLY;
                $path = "/{$locale}/{$page->slug}";

                if ($page->isBlogArticle()) {
                    $priority = 0.85;
                    $frequency = Url::CHANGE_FREQUENCY_WEEKLY;
                    $path = "/{$locale}/blog/{$page->slug}";
                } elseif ($page->slug === 'privacy-policy') {
                    $priority = 0.8;
                    $frequency = Url::CHANGE_FREQUENCY_WEEKLY;
                }

                $sitemap->add(Url::create($path)
                    ->setChangeFrequency($frequency)
                    ->setPriority($priority)
                    ->setLastModificationDate($page->updated_at));
            }
        }

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $blogCount = Page::blogArticles()->count();
        $this->info('Sitemap generated successfully! (' . count($locales) . ' locales, ' . $pages->count() . ' pages, ' . $blogCount . ' blog articles)');
    }
}
