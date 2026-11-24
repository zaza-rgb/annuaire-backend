<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'traditional_celebration' => ['fr' => 'Fête traditionnelle', 'en' => 'Traditional celebration', 'pt' => 'Festa tradicional'],
            'business_meeting' => ['fr' => "Rencontre d'affaires", 'en' => 'Business meeting', 'pt' => 'Encontro de negócios'],
            'worship' => ['fr' => 'Culte', 'en' => 'Worship service', 'pt' => 'Culto'],
            'sport' => ['fr' => 'Sport', 'en' => 'Sport', 'pt' => 'Esporte'],
            'students' => ['fr' => 'Étudiants', 'en' => 'Students', 'pt' => 'Estudantes'],
        ];

        $languages = DB::table('languages')->pluck('id', 'code');

        foreach ($categories as $code => $names) {
            DB::table('event_categories')->updateOrInsert(
                ['code' => $code],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $categoryId = DB::table('event_categories')->where('code', $code)->value('id');

            foreach ($names as $languageCode => $name) {
                DB::table('event_category_translations')->updateOrInsert(
                    ['event_category_id' => $categoryId, 'language_id' => $languages[$languageCode]],
                    ['name' => $name, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
}