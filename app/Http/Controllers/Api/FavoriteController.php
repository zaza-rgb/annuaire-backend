<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    // GET /api/favorites : mes commerces favoris (fiches validées uniquement)
    public function index(Request $request)
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $favorites = $request->user()->favoriteBusinesses()
            ->where('businesses.status', 'approved')
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
            ->withAvg(['reviews as average_rating' => fn ($q) => $q->where('status', 'approved')], 'rating')
            ->orderByDesc('favorites.created_at')
            ->paginate($data['per_page'] ?? 20);

        // Masque l'objet pivot, inutile pour l'interface
        $favorites->getCollection()->each(fn ($business) => $business->makeHidden('pivot'));

        return response()->json($favorites);
    }

    // POST /api/businesses/{id}/favorite : ajouter un favori
    public function store(Request $request, $id)
    {
        $business = Business::where('status', 'approved')->findOrFail($id);

        $result = $request->user()->favoriteBusinesses()->syncWithoutDetaching([$business->id]);

        // 201 si le favori vient d'être créé, 200 s'il existait déjà
        return response()->json(
            ['business_id' => $business->id, 'is_favorite' => true],
            count($result['attached']) > 0 ? 201 : 200
        );
    }

    // DELETE /api/businesses/{id}/favorite : retirer un favori
    public function destroy(Request $request, $id)
    {
        $request->user()->favoriteBusinesses()->detach($id);

        return response()->noContent();
    }
}
