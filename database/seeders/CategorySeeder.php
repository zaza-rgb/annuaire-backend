<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'restaurant' => ['fr' => 'Restaurant', 'en' => 'Restaurant', 'pt' => 'Restaurante'],
            'shop' => ['fr' => 'Boutique', 'en' => 'Shop', 'pt' => 'Loja'],
            'hair_salon' => ['fr' => 'Salon de coiffure', 'en' => 'Hair salon', 'pt' => 'Salão de cabeleireiro'],
            'tailor' => ['fr' => 'Atelier de couture', 'en' => 'Tailor shop', 'pt' => 'Ateliê de costura'],
            'african_products' => ['fr' => 'Produits africains', 'en' => 'African products', 'pt' => 'Produtos africanos'],
        ];

        $languages = DB::table('languages')->pluck('id', 'code');

        foreach ($categories as $code => $names) {
            DB::table('categories')->updateOrInsert(
                ['code' => $code],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $categoryId = DB::table('categories')->where('code', $code)->value('id');

            foreach ($names as $languageCode => $name) {
                DB::table('category_translations')->updateOrInsert(
                    ['category_id' => $categoryId, 'language_id' => $languages[$languageCode]],
                    ['name' => $name, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
}