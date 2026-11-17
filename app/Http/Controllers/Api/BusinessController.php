<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q'           => ['nullable', 'string', 'max:100'],
            'city'        => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'country_id'  => ['nullable', 'integer', 'exists:countries,id'],
            'lat'         => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng,radius'],
            'lng'         => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat,radius'],
            'radius'      => ['nullable', 'numeric', 'min:0.1', 'max:100', 'required_with:lat,lng'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Seules les fiches validées sont visibles
        $query = Business::query()->where('status', 'approved');

        if (!empty($data['q'])) {
            $term = '%' . $data['q'] . '%';
            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('address', 'like', $term);
            });
        }
        if (!empty($data['city'])) {
            $query->where('city', $data['city']);
        }
        if (!empty($data['category_id'])) {
            $query->where('category_id', $data['category_id']);
        }
        if (!empty($data['country_id'])) {
            $query->where('country_id', $data['country_id']);
        }

        if (isset($data['lat'])) {
            // Formule de Haversine : distance en km
            $distance = '(6371 * acos(least(1, greatest(-1,
                cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
                + sin(radians(?)) * sin(radians(latitude))
            ))))';

            $query->select('businesses.*')
                ->selectRaw("$distance as distance_km", [$data['lat'], $data['lng'], $data['lat']])
                ->having('distance_km', '<=', $data['radius'])
                ->orderBy('distance_km');
        } else {
            $query->orderBy('name');
        }

        // Note moyenne et nombre d'avis (avis validés uniquement)
        $query->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
              ->withAvg(['reviews as average_rating' => fn ($q) => $q->where('status', 'approved')], 'rating');

        return response()->json($query->paginate($data['per_page'] ?? 20));
    }

    public function cities()
    {
        return Business::where('status', 'approved')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');
    }

    public function show($id)
    {
        $business = Business::where('status', 'approved')
            ->with([
                'category',
                'country',
                'languages',
                'openingHours' => fn ($q) => $q->orderBy('day_of_week')->orderBy('opens_at'),
                'photos' => fn ($q) => $q->where('status', 'approved')
                                         ->select('id', 'business_id', 'review_id', 'file_path'),
                'reviews' => fn ($q) => $q->where('status', 'approved')
                                          ->with('user:id,name')
                                          ->latest(),
            ])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
            ->withAvg(['reviews as average_rating' => fn ($q) => $q->where('status', 'approved')], 'rating')
            ->findOrFail($id);

        return response()->json($business);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'country_id'  => ['required', 'integer', 'exists:countries,id'],
            'city'        => ['required', 'string', 'max:100'],
            'address'     => ['required', 'string', 'max:255'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'pricing'     => ['nullable', 'string'],
        ]);

        $user = $request->user();

        // Ces trois champs sont fixés par le serveur, jamais par le client
        $data['status']      = 'pending';
        $data['proposed_by'] = $user->id;
        $data['owner_id']    = $user->role === 'merchant' ? $user->id : null;

        $business = Business::create($data);

        return response()->json($business, 201);
    }

    public function mine(Request $request)
    {
        $user = $request->user();

        // Réservé au rôle merchant, comme les revendications
        if ($user->role !== 'merchant') {
            return response()->json([
                'message' => 'Seul un compte commerçant a un tableau de bord.',
            ], 403);
        }

        $data = $request->validate([
            'status'   => ['nullable', 'in:pending,approved,rejected'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Toutes les fiches du commerçant, quel que soit leur statut
        $query = Business::where('owner_id', $user->id);

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        // Même calcul que dans index() : avis validés uniquement
        $query->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
              ->withAvg(['reviews as average_rating' => fn ($q) => $q->where('status', 'approved')], 'rating');

        return response()->json(
            $query->latest()->paginate($data['per_page'] ?? 20)
        );
    }
    public function update(Request $request, $id)
{
    $business = Business::findOrFail($id);
    $user = $request->user();

    // Réservé au propriétaire de la fiche
    if ($business->owner_id !== $user->id) {
        return response()->json([
            'message' => 'Vous ne pouvez modifier que vos propres fiches.',
        ], 403);
    }

    $data = $request->validate([
        'description'    => ['nullable', 'string'],
        'pricing'        => ['nullable', 'string'],
        'phone'          => ['nullable', 'string', 'max:20'],
        'country_id'     => ['sometimes', 'integer', 'exists:countries,id'],
        'language_ids'   => ['sometimes', 'array'],
        'language_ids.*' => ['integer', 'exists:languages,id'],
    ]);

    // Les langues passent par la relation, pas par une colonne
    $languageIds = $data['language_ids'] ?? null;
    unset($data['language_ids']);

    // status, owner_id, proposed_by : jamais modifiables ici
    $business->update($data);

    if ($languageIds !== null) {
        $business->languages()->sync($languageIds);
    }

    $business->load(['category', 'country', 'languages'])
        ->loadCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
        ->loadAvg(['reviews as average_rating' => fn ($q) => $q->where('status', 'approved')], 'rating');

    return response()->json($business);
    
    }
}