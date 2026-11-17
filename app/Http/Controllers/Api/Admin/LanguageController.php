<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    public function store(Request $request)
    {
        if ($request->has('code')) {
            $request->merge(['code' => strtolower((string) $request->input('code'))]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[a-z]{2}$/', 'unique:languages,code'],
            'name' => ['required', 'string', 'max:50'],
        ]);

        $language = Language::create($data);

        return response()->json($language, 201);
    }

    public function update(Request $request, $id)
    {
        $language = Language::find($id);
        if (!$language) {
            return response()->json(['message' => 'Langue introuvable.'], 404);
        }

        if ($request->has('code')) {
            $request->merge(['code' => strtolower((string) $request->input('code'))]);
        }

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'regex:/^[a-z]{2}$/',
                       Rule::unique('languages', 'code')->ignore($language->id)],
            'name' => ['sometimes', 'string', 'max:50'],
        ]);

        // Les langues de base ne changent pas de code (l'interface et les noms traduits en dépendent)
        if (isset($data['code'])
            && $data['code'] !== $language->code
            && in_array($language->code, ['fr', 'en', 'pt'], true)) {
            return response()->json([
                'message' => 'Le code des langues fr, en et pt ne peut pas être modifié.',
                'errors' => ['code' => ['Le code des langues fr, en et pt ne peut pas être modifié.']],
            ], 422);
        }

        $language->update($data);

        return response()->json($language->fresh(), 200);
    }

    public function destroy($id)
    {
        $language = Language::find($id);
        if (!$language) {
            return response()->json(['message' => 'Langue introuvable.'], 404);
        }

        $used = User::where('language_id', $language->id)->exists()
            || DB::table('business_language')->where('language_id', $language->id)->exists()
            || DB::table('category_translations')->where('language_id', $language->id)->exists()
            || DB::table('country_translations')->where('language_id', $language->id)->exists();

        if ($used) {
            return response()->json([
                'message' => 'Cette langue est utilisée par des comptes, des fiches ou des traductions : suppression impossible.',
            ], 409);
        }

        $language->delete();

        return response()->noContent(); // 204
    }
}