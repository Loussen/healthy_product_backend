<?php

namespace App\Console\Commands;

use App\Models\Page;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the sitemap.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sitemap = Sitemap::create();
        $locales = array_keys(config('services.locales', []));

        // Ana sayfa (root)
        $sitemap->add(Url::create('/')
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            ->setPriority(1.0));

        // Her dil için ana sayfa
        foreach ($locales as $locale) {
            $sitemap->add(Url::create("/{$locale}")
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(1.0));
        }

        // Tüm sayfaları al ve her dil için ekle
        $pages = Page::all();

        foreach ($pages as $page) {
            foreach ($locales as $locale) {
                $priority = 0.7;
                $frequency = Url::CHANGE_FREQUENCY_MONTHLY;

                // Önemli sayfalar için öncelik artır
                if ($page->slug === 'privacy-policy') {
                    $priority = 0.8;
                    $frequency = Url::CHANGE_FREQUENCY_WEEKLY;
                }

                $sitemap->add(Url::create("/{$locale}/{$page->slug}")
                    ->setChangeFrequency($frequency)
                    ->setPriority($priority)
                    ->setLastModificationDate($page->updated_at));
            }
        }

        // Sitemap'i public klasörüne kaydet
        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap generated successfully! (' . count($locales) . ' locales, ' . $pages->count() . ' pages)');
    }
}
