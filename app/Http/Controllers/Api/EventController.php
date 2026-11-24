<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
      // Liste publique : événements approuvés et pas encore terminés.
    // Avec lat, lng et radius (km) : seulement les événements qui ont des coordonnées
    // dans ce rayon, du plus proche au plus lointain.
    public function index(Request $request)
    {
        $data = $request->validate([
            'city'              => 'sometimes|string|max:100',
            'language_id'       => 'sometimes|integer|exists:languages,id',
            'event_category_id' => 'sometimes|integer|exists:event_categories,id',
            'date_from'         => 'sometimes|date',
            'date_to'           => 'sometimes|date|after_or_equal:date_from',
            'lat'               => 'nullable|numeric|between:-90,90|required_with:lng,radius',
            'lng'               => 'nullable|numeric|between:-180,180|required_with:lat,radius',
            'radius'            => 'nullable|numeric|min:0.1|max:100|required_with:lat,lng',
            'per_page'          => 'sometimes|integer|min:1|max:50',
        ]);

        $query = $this->publicQuery()->where('ends_at', '>=', now());

        if (isset($data['city'])) {
            $query->where('city', 'like', '%' . $data['city'] . '%');
        }

        if (isset($data['language_id'])) {
            $query->whereHas('languages', fn ($q) => $q->where('languages.id', $data['language_id']));
        }

        if (isset($data['event_category_id'])) {
            $query->where('event_category_id', $data['event_category_id']);
        }

        if (isset($data['date_from'])) {
            $query->whereDate('starts_at', '>=', $data['date_from']);
        }

        if (isset($data['date_to'])) {
            $query->whereDate('starts_at', '<=', $data['date_to']);
        }

        $nearby = isset($data['lat']);

        if ($nearby) {
            // Formule de Haversine : distance en km (même formule que les commerces)
            $distance = '(6371 * acos(least(1, greatest(-1,
                cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
                + sin(radians(?)) * sin(radians(latitude))
            ))))';

            $query->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->selectRaw("$distance as distance_km", [$data['lat'], $data['lng'], $data['lat']])
                ->having('distance_km', '<=', $data['radius'])
                ->orderBy('distance_km');
        }

        return $query->orderBy('starts_at')
            ->paginate($data['per_page'] ?? 20)
            ->through(fn (Event $event) => $nearby
                ? array_merge($this->present($event), ['distance_km' => round($event->distance_km, 1)])
                : $this->present($event));
    }

    // Fiche publique : seulement un événement approuvé.
    public function show($id)
    {
        $event = $this->publicQuery()->find($id);

        if (!$event) {
            return response()->json(['message' => 'Événement introuvable.'], 404);
        }

        return response()->json($this->present($event));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'             => 'required|string|max:150',
            'description'       => 'nullable|string|max:5000',
            'event_category_id' => 'required|exists:event_categories,id',
            'language_ids'      => 'required|array|min:1',
            'language_ids.*'    => 'integer|exists:languages,id',
            'starts_at'         => 'required|date|after:now',
            'ends_at'           => 'required|date|after:starts_at',
            'city'              => 'required|string|max:100',
            'address'           => 'required|string|max:255',
            'latitude'          => 'nullable|numeric|between:-90,90',
            'longitude'         => 'nullable|numeric|between:-180,180',
            'capacity'          => 'required|integer|min:1|max:100000',
        ]);

        $event = Event::create([
            'title'             => $data['title'],
            'description'       => $data['description'] ?? null,
            'event_category_id' => $data['event_category_id'],
            'starts_at'         => $data['starts_at'],
            'ends_at'           => $data['ends_at'],
            'city'              => $data['city'],
            'address'           => $data['address'],
            'latitude'          => $data['latitude'] ?? null,
            'longitude'         => $data['longitude'] ?? null,
            'capacity'          => $data['capacity'],
            'organizer_id'      => $request->user()->id,
            // Le statut est fixé par le serveur : l'admin valide avant publication.
            'status'            => 'pending',
        ]);

        $event->languages()->sync($data['language_ids']);

        return response()->json($event->load(['category', 'languages']), 201);
    }

    // Modification partielle par l'organisateur propriétaire.
    public function update(Request $request, $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Événement introuvable.'], 404);
        }

        if ($event->organizer_id !== $request->user()->id) {
            return response()->json(['message' => 'Cet événement ne vous appartient pas.'], 403);
        }

        if ($event->status === 'cancelled') {
            return response()->json(['message' => 'Un événement annulé ne peut pas être modifié.'], 409);
        }

        if ($event->ends_at->isPast()) {
            return response()->json(['message' => 'Cet événement est terminé.'], 409);
        }

        // status et organizer_id ne sont pas acceptés ici : ils sont ignorés.
        $data = $request->validate([
            'title'             => 'sometimes|string|max:150',
            'description'       => 'sometimes|nullable|string|max:5000',
            'event_category_id' => 'sometimes|exists:event_categories,id',
            'language_ids'      => 'sometimes|array|min:1',
            'language_ids.*'    => 'integer|exists:languages,id',
            'starts_at'         => 'sometimes|date|after:now',
            'ends_at'           => 'sometimes|date',
            'city'              => 'sometimes|string|max:100',
            'address'           => 'sometimes|string|max:255',
            'latitude'          => 'sometimes|nullable|numeric|between:-90,90',
            'longitude'         => 'sometimes|nullable|numeric|between:-180,180',
            'capacity'          => 'sometimes|integer|min:1|max:100000',
        ]);

        if (empty($data)) {
            return response()->json(['message' => 'Aucun champ à modifier.'], 422);
        }

        // La fin doit rester après le début, même si un seul des deux est modifié.
        $start = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : $event->starts_at;
        $end = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : $event->ends_at;

        if ($end->lte($start)) {
            throw ValidationException::withMessages([
                'ends_at' => ['La fin doit être après le début.'],
            ]);
        }

        // On ne peut pas réduire les places sous le nombre d'inscrits.
        if (isset($data['capacity'])) {
            $registered = EventRegistration::where('event_id', $event->id)
                ->where('status', 'registered')
                ->count();

            if ($data['capacity'] < $registered) {
                throw ValidationException::withMessages([
                    'capacity' => ["Il y a déjà {$registered} inscrits : le nombre de places ne peut pas être plus bas."],
                ]);
            }
        }

        $event->fill(Arr::only($data, [
            'title', 'description', 'event_category_id', 'starts_at', 'ends_at',
            'city', 'address', 'latitude', 'longitude', 'capacity',
        ]));

        // Revalidation par l'administrateur : événement refusé modifié, ou événement
        // approuvé dont le titre, la description, la ville ou l'adresse changent.
        $resubmit = $event->status === 'rejected'
            || ($event->status === 'approved' && $event->isDirty(['title', 'description', 'city', 'address']));

        if ($resubmit) {
            $event->status = 'pending';
        }

        $event->save();

        if (isset($data['language_ids'])) {
            $event->languages()->sync($data['language_ids']);
        }

        return response()->json($event->load(['category', 'languages']));
    }

    // Annulation par l'organisateur propriétaire.
    public function cancel(Request $request, $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Événement introuvable.'], 404);
        }

        if ($event->organizer_id !== $request->user()->id) {
            return response()->json(['message' => 'Cet événement ne vous appartient pas.'], 403);
        }

        if ($event->status === 'cancelled') {
            return response()->json(['message' => 'Cet événement est déjà annulé.'], 409);
        }

        if ($event->ends_at->isPast()) {
            return response()->json(['message' => 'Cet événement est terminé.'], 409);
        }

        $event->status = 'cancelled';
        $event->save();

        return response()->json($event->load(['category', 'languages']));
    }

    // Mes événements organisés : tous les statuts, y compris passés et annulés.
    public function organized(Request $request)
    {
        $data = $request->validate([
            'status'   => 'sometimes|in:pending,approved,rejected,cancelled',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Event::where('organizer_id', $request->user()->id)
            ->with(['category.translations', 'languages', 'organizer:id,name'])
            ->withCount([
                'registrations as registered_count' => fn ($q) => $q->where('status', 'registered'),
                'registrations as checked_in_count' => fn ($q) => $q->where('status', 'registered')->whereNotNull('checked_in_at'),
            ]);

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return $query->orderByDesc('starts_at')
            ->paginate($data['per_page'] ?? 20)
            ->through(fn (Event $event) => array_merge($this->present($event), [
                'status'           => $event->status,
                'checked_in_count' => $event->checked_in_count,
            ]));
    }

    private function publicQuery()
    {
        return Event::where('status', 'approved')
            ->with(['category.translations', 'languages', 'organizer:id,name'])
            ->withCount(['registrations as registered_count' => fn ($q) => $q->where('status', 'registered')]);
    }

    // Forme publique : pas d'e-mail ni d'id interne de l'organisateur.
    private function present(Event $event): array
    {
        return [
            'id'               => $event->id,
            'title'            => $event->title,
            'description'      => $event->description,
            'photo_path'       => $event->photo_path,
            'city'             => $event->city,
            'address'          => $event->address,
            'latitude'         => $event->latitude !== null ? (float) $event->latitude : null,
            'longitude'        => $event->longitude !== null ? (float) $event->longitude : null,
            'starts_at'        => $event->starts_at?->format('Y-m-d H:i:s'),
            'ends_at'          => $event->ends_at?->format('Y-m-d H:i:s'),
            'capacity'         => $event->capacity,
            'registered_count' => $event->registered_count,
            'places_left'      => max(0, $event->capacity - $event->registered_count),
            'category'         => ['id' => $event->category->id, 'code' => $event->category->code, 'name' => $event->category->name],
            'languages'        => $event->languages->map(fn ($l) => ['id' => $l->id, 'code' => $l->code, 'name' => $l->name])->values(),
            'organizer'        => ['name' => $event->organizer->name],
        ];
    }
}
