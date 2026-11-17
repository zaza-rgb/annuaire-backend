<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            'TG' => ['fr' => 'Togo', 'en' => 'Togo', 'pt' => 'Togo'],
            'NG' => ['fr' => 'Nigéria', 'en' => 'Nigeria', 'pt' => 'Nigéria'],
            'SN' => ['fr' => 'Sénégal', 'en' => 'Senegal', 'pt' => 'Senegal'],
            'GH' => ['fr' => 'Ghana', 'en' => 'Ghana', 'pt' => 'Gana'],
            'CI' => ['fr' => "Côte d'Ivoire", 'en' => 'Ivory Coast', 'pt' => 'Costa do Marfim'],
            'CM' => ['fr' => 'Cameroun', 'en' => 'Cameroon', 'pt' => 'Camarões'],
            'BJ' => ['fr' => 'Bénin', 'en' => 'Benin', 'pt' => 'Benim'],
            'CD' => ['fr' => 'RD Congo', 'en' => 'DR Congo', 'pt' => 'RD Congo'],
            'AO' => ['fr' => 'Angola', 'en' => 'Angola', 'pt' => 'Angola'],
            'MZ' => ['fr' => 'Mozambique', 'en' => 'Mozambique', 'pt' => 'Moçambique'],
            'CV' => ['fr' => 'Cap-Vert', 'en' => 'Cape Verde', 'pt' => 'Cabo Verde'],
            'GW' => ['fr' => 'Guinée-Bissau', 'en' => 'Guinea-Bissau', 'pt' => 'Guiné-Bissau'],
        ];

        $languages = DB::table('languages')->pluck('id', 'code');

        foreach ($countries as $code => $names) {
            DB::table('countries')->updateOrInsert(
                ['code' => $code],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $countryId = DB::table('countries')->where('code', $code)->value('id');

            foreach ($names as $languageCode => $name) {
                DB::table('country_translations')->updateOrInsert(
                    ['country_id' => $countryId, 'language_id' => $languages[$languageCode]],
                    ['name' => $name, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
}