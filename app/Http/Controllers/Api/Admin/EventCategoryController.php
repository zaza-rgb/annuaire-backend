<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventCategoryController extends Controller
{
    private function respond(EventCategory $category)
    {
        $category->load('translations.language');
        $category->makeVisible('translations');

        return $category;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:event_categories,code'],
            'translations' => ['required', 'array'],
            'translations.fr' => ['required', 'string', 'max:100'],
            'translations.en' => ['required', 'string', 'max:100'],
            'translations.pt' => ['required', 'string', 'max:100'],
        ]);

        $category = DB::transaction(function () use ($data) {
            $category = EventCategory::create(['code' => $data['code']]);
            $this->saveTranslations($category, $data['translations']);
            return $category;
        });

        return response()->json($this->respond($category), 201);
    }

    public function update(Request $request, $id)
    {
        $category = EventCategory::find($id);
        if (! $category) {
            return response()->json(['message' => 'Catégorie introuvable.'], 404);
        }

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('event_categories', 'code')->ignore($category->id)],
            'translations' => ['sometimes', 'array'],
            'translations.fr' => ['sometimes', 'string', 'max:100'],
            'translations.en' => ['sometimes', 'string', 'max:100'],
            'translations.pt' => ['sometimes', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($category, $data) {
            if (isset($data['code'])) {
                $category->update(['code' => $data['code']]);
            }
            if (isset($data['translations'])) {
                $this->saveTranslations($category, $data['translations']);
            }
        });

        return $this->respond($category->fresh());
    }

    public function destroy($id)
    {
        $category = EventCategory::find($id);
        if (! $category) {
            return response()->json(['message' => 'Catégorie introuvable.'], 404);
        }

        if (Event::where('event_category_id', $category->id)->exists()) {
            return response()->json(['message' => 'Cette catégorie est utilisée par des événements : suppression impossible.'], 409);
        }

        DB::transaction(function () use ($category) {
            $category->translations()->delete();
            $category->delete();
        });

        return response()->noContent();
    }

    private function saveTranslations(EventCategory $category, array $translations): void
    {
        foreach ($translations as $code => $name) {
            $languageId = Language::where('code', $code)->value('id');
            if ($languageId) {
                $category->translations()->updateOrCreate(
                    ['language_id' => $languageId],
                    ['name' => $name]
                );
            }
        }
    }
}