<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CountryController extends Controller
{
    private function respond(Country $country)
    {
        $country->load('translations.language');
        $country->makeVisible('translations');

        return $country;
    }

    public function store(Request $request)
    {
        if ($request->has('code')) {
            $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/', 'unique:countries,code'],
            'translations' => ['required', 'array'],
            'translations.fr' => ['required', 'string', 'max:100'],
            'translations.en' => ['required', 'string', 'max:100'],
            'translations.pt' => ['required', 'string', 'max:100'],
        ]);

        $country = DB::transaction(function () use ($data) {
            $country = Country::create(['code' => $data['code']]);
            $this->saveTranslations($country, $data['translations']);
            return $country;
        });

        return response()->json($this->respond($country), 201);
    }

    public function update(Request $request, $id)
    {
        $country = Country::find($id);
        if (! $country) {
            return response()->json(['message' => 'Pays introuvable.'], 404);
        }

        if ($request->has('code')) {
            $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        }

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'size:2', 'regex:/^[A-Z]{2}$/', Rule::unique('countries', 'code')->ignore($country->id)],
            'translations' => ['sometimes', 'array'],
            'translations.fr' => ['sometimes', 'string', 'max:100'],
            'translations.en' => ['sometimes', 'string', 'max:100'],
            'translations.pt' => ['sometimes', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($country, $data) {
            if (isset($data['code'])) {
                $country->update(['code' => $data['code']]);
            }
            if (isset($data['translations'])) {
                $this->saveTranslations($country, $data['translations']);
            }
        });

        return $this->respond($country->fresh());
    }

    public function destroy($id)
    {
        $country = Country::find($id);
        if (! $country) {
            return response()->json(['message' => 'Pays introuvable.'], 404);
        }

        if ($country->businesses()->exists()) {
            return response()->json(['message' => 'Ce pays est utilisé par des fiches : suppression impossible.'], 409);
        }

        DB::transaction(function () use ($country) {
            $country->translations()->delete();
            $country->delete();
        });

        return response()->noContent();
    }

    private function saveTranslations(Country $country, array $translations): void
    {
        foreach ($translations as $code => $name) {
            $languageId = Language::where('code', $code)->value('id');
            if ($languageId) {
                $country->translations()->updateOrCreate(
                    ['language_id' => $languageId],
                    ['name' => $name]
                );
            }
        }
    }
}