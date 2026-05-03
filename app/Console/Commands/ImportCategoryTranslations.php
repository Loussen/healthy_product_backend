<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportCategoryTranslations extends Command
{
    protected $signature = 'import:category-translations
                            {--path= : JSON faylı (phpMyAdmin export formatı)}
                            {--dry-run : Yalnız sətirləri göstər, bazaya yazma}';

    protected $description = 'categories cədvəlində name, slug, description sahələrini JSON faylından yeniləyir';

    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('data/categories_with_new_locales.json');

        if (! File::exists($path)) {
            $this->error("Fayl tapılmadı: {$path}");
            $this->line('JSON-u buraya qoyun: database/data/categories_with_new_locales.json');
            $this->line('və ya: php artisan import:category-translations --path=/tam/yol.json');

            return self::FAILURE;
        }

        $doc = json_decode(File::get($path), true);
        if (! is_array($doc)) {
            $this->error('JSON oxunmadı və ya format səhvdir.');

            return self::FAILURE;
        }

        $table = collect($doc)->first(
            fn ($x) => is_array($x)
                && ($x['type'] ?? null) === 'table'
                && ($x['name'] ?? null) === 'categories'
        );

        if (! $table || empty($table['data']) || ! is_array($table['data'])) {
            $this->error('JSON-da type=table, name=categories və data massivi gözlənilir (phpMyAdmin export).');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        foreach ($table['data'] as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }

            $payload = [
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'updated_at' => now(),
            ];
            if (array_key_exists('description', $row)) {
                $payload['description'] = $row['description'];
            }

            if ($dry) {
                $this->line("yenilənəcək id={$id}");
            } else {
                DB::table('categories')->where('id', $id)->update($payload);
            }
        }

        $this->info($dry ? 'Dry-run: heç bir dəyişiklik yazılmadı.' : 'Kateqoriya tərcümələri yeniləndi.');

        return self::SUCCESS;
    }
}
