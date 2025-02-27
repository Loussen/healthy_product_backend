<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => json_encode([
                    'az' => 'Ümumi',
                    'tr' => 'Genel',
                    'ru' => 'Общий',
                    'en' => 'General'
                ]),
                'slug' => json_encode([
                    'az' => 'umumi',
                    'tr' => 'genel',
                    'ru' => 'obshiy',
                    'en' => 'general'
                ]),
                'icon' => 'category',
                'color' => '#2196F3',
            ],
            [
                'name' => json_encode([
                    'az' => 'Uşaq',
                    'tr' => 'Çocuk',
                    'ru' => 'Дети',
                    'en' => 'Children'
                ]),
                'slug' => json_encode([
                    'az' => 'usaq',
                    'tr' => 'cocuk',
                    'ru' => 'deti',
                    'en' => 'children'
                ]),
                'icon' => 'child_care',
                'color' => '#9C27B0',
            ],
            [
                'name' => json_encode([
                    'az' => 'Vegetarian',
                    'tr' => 'Vejetaryen',
                    'ru' => 'Вегетарианец',
                    'en' => 'Vegetarian'
                ]),
                'slug' => json_encode([
                    'az' => 'vegetarian',
                    'tr' => 'vejetaryen',
                    'ru' => 'vegetarianets',
                    'en' => 'vegetarian'
                ]),
                'icon' => 'restaurant',
                'color' => '#009688',
            ],
            [
                'name' => json_encode([
                    'az' => 'Diabet',
                    'tr' => 'Diyabet',
                    'ru' => 'Диабет',
                    'en' => 'Diabetes'
                ]),
                'slug' => json_encode([
                    'az' => 'diabet',
                    'tr' => 'diyabet',
                    'ru' => 'diabet',
                    'en' => 'diabetes'
                ]),
                'icon' => 'medical_services',
                'color' => '#F44336',
            ],
            [
                'name' => json_encode([
                    'az' => 'Qlüten',
                    'tr' => 'Gluten',
                    'ru' => 'Глютен',
                    'en' => 'Gluten'
                ]),
                'slug' => json_encode([
                    'az' => 'qluten',
                    'tr' => 'gluten',
                    'ru' => 'gluten',
                    'en' => 'gluten'
                ]),
                'icon' => 'no_food',
                'color' => '#FF9800',
            ],
            [
                'name' => json_encode([
                    'az' => 'Vegan',
                    'tr' => 'Vegan',
                    'ru' => 'Веган',
                    'en' => 'Vegan'
                ]),
                'slug' => json_encode([
                    'az' => 'vegan',
                    'tr' => 'vegan',
                    'ru' => 'vegan',
                    'en' => 'vegan'
                ]),
                'icon' => 'eco',
                'color' => '#4CAF50',
            ],
            [
                'name' => json_encode([
                    'az' => 'İdmançı',
                    'tr' => 'Sporcu',
                    'ru' => 'Спортсмен',
                    'en' => 'Athlete'
                ]),
                'slug' => json_encode([
                    'az' => 'idmanci',
                    'tr' => 'sporcu',
                    'ru' => 'sportsmen',
                    'en' => 'athlete'
                ]),
                'icon' => 'fitness_center',
                'color' => '#3F51B5',
            ],
            [
                'name' => json_encode([
                    'az' => 'Kosmetika',
                    'tr' => 'Kozmetik',
                    'ru' => 'Косметика',
                    'en' => 'Cosmetics'
                ]),
                'slug' => json_encode([
                    'az' => 'kosmetika',
                    'tr' => 'kozmetik',
                    'ru' => 'kosmetika',
                    'en' => 'cosmetics'
                ]),
                'icon' => 'face',
                'color' => '#E91E63',
            ],
            [
                'name' => json_encode([
                    'az' => 'Körpə',
                    'tr' => 'Bebek',
                    'ru' => 'Малыш',
                    'en' => 'Baby'
                ]),
                'slug' => json_encode([
                    'az' => 'korpe',
                    'tr' => 'bebek',
                    'ru' => 'malish',
                    'en' => 'baby'
                ]),
                'icon' => 'baby_changing_station',
                'color' => '#03A9F4',
            ],
            [
                'name' => json_encode([
                    'az' => 'Orqanik',
                    'tr' => 'Organik',
                    'ru' => 'Органический',
                    'en' => 'Organic'
                ]),
                'slug' => json_encode([
                    'az' => 'orqanik',
                    'tr' => 'organik',
                    'ru' => 'organicheskiy',
                    'en' => 'organic'
                ]),
                'icon' => 'grass',
                'color' => '#8BC34A',
            ],
            [
                'name' => json_encode([
                    'az' => 'Allergiya',
                    'tr' => 'Alerjik',
                    'ru' => 'Аллергический',
                    'en' => 'Allergic'
                ]),
                'slug' => json_encode([
                    'az' => 'allergiya',
                    'tr' => 'alerjik',
                    'ru' => 'allergicheskiy',
                    'en' => 'allergic'
                ]),
                'icon' => 'healing',
                'color' => '#FFC107',
            ],
            [
                'name' => json_encode([
                    'az' => 'Hamilə',
                    'tr' => 'Hamile',
                    'ru' => 'Беременная',
                    'en' => 'Pregnant'
                ]),
                'slug' => json_encode([
                    'az' => 'hamile',
                    'tr' => 'hamile',
                    'ru' => 'beremennaya',
                    'en' => 'pregnant'
                ]),
                'icon' => 'pregnant_woman',
                'color' => '#673AB7',
            ],
            [
                'name' => json_encode([
                    'az' => 'Yaşlı',
                    'tr' => 'Yaşlı',
                    'ru' => 'Пожилой',
                    'en' => 'Elderly'
                ]),
                'slug' => json_encode([
                    'az' => 'yasli',
                    'tr' => 'yasli',
                    'ru' => 'pozhiloy',
                    'en' => 'elderly'
                ]),
                'icon' => 'elderly',
                'color' => '#795548',
            ],
            [
                'name' => json_encode([
                    'az' => 'Ürək',
                    'tr' => 'Kalp',
                    'ru' => 'Сердце',
                    'en' => 'Heart'
                ]),
                'slug' => json_encode([
                    'az' => 'urek',
                    'tr' => 'kalp',
                    'ru' => 'serdtse',
                    'en' => 'heart'
                ]),
                'icon' => 'favorite',
                'color' => '#EF5350',
            ],
            [
                'name' => json_encode([
                    'az' => 'Çəki Nəzarəti',
                    'tr' => 'Kilo Kontrolü',
                    'ru' => 'Контроль Веса',
                    'en' => 'Weight Control'
                ]),
                'slug' => json_encode([
                    'az' => 'ceki-nezareti',
                    'tr' => 'kilo-kontrolu',
                    'ru' => 'kontrol-vesa',
                    'en' => 'weight-control'
                ]),
                'icon' => 'monitor_weight',
                'color' => '#FF5722',
            ],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'name' => $category['name'],
                'slug' => $category['slug'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
