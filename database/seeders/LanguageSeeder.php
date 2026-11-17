<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'fr', 'name' => 'Français'],
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'pt', 'name' => 'Português'],
        ];

        foreach ($languages as $language) {
            DB::table('languages')->updateOrInsert(
                ['code' => $language['code']],
                ['name' => $language['name'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}