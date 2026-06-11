<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class BlogArticlesSeeder extends Seeder
{
    public function run(): void
    {
        $articles = require database_path('data/blog_articles.php');

        foreach ($articles as $article) {
            Page::updateOrCreate(
                ['slug' => $article['slug']],
                [
                    'template' => 'blog_article',
                    'name' => $article['name'],
                    'title' => $article['title'],
                    'content' => $article['content'],
                ]
            );
        }

        $this->command->info('Seeded ' . count($articles) . ' blog articles.');
    }
}
