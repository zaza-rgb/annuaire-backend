<?php

namespace App\Http\Controllers\Api\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;

class EventRegistrationController extends Controller
{
    // Liste des inscrits d'un de mes événements (nom seulement, pas d'e-mail ni de téléphone :
    // pour écrire à un inscrit, l'organisateur passera par la messagerie).
    public function index(Request $request, $id)
    {
        $event = $this->ownEvent($request, $id);

        if (!$event) {
            return response()->json(['message' => 'Événement introuvable.'], 404);
        }

        $registrations = EventRegistration::with('user:id,name')
            ->where('event_id', $event->id)
            ->where('status', 'registered')
            ->orderBy('id')
            ->get();

        return response()->json([
            'event_id'         => $event->id,
            'capacity'         => $event->capacity,
            'registered_count' => $registrations->count(),
            'checked_in_count' => $registrations->whereNotNull('checked_in_at')->count(),
            'registrations'    => $registrations->map(fn ($r) => [
                'id'            => $r->id,
                'user'          => ['id' => $r->user->id, 'name' => $r->user->name],
                'entry_code'    => $r->entry_code,
                'checked_in_at' => $r->checked_in_at?->format('Y-m-d H:i:s'),
            ])->values(),
        ]);
    }

    // Enregistrer la présence à l'entrée avec le code (saisi ou lu depuis le QR).
    public function checkIn(Request $request, $id)
    {
        $event = $this->ownEvent($request, $id);

        if (!$event) {
            return response()->json(['message' => 'Événement introuvable.'], 404);
        }

        $data = $request->validate([
            'entry_code' => 'required|string|max:20',
        ]);

        $code = strtoupper(trim($data['entry_code']));

        $registration = EventRegistration::with('user:id,name')
            ->where('event_id', $event->id)
            ->where('entry_code', $code)
            ->where('status', 'registered')
            ->first();

        if (!$registration) {
            return response()->json(['message' => 'Code invalide pour cet événement.'], 404);
        }

        if ($registration->checked_in_at) {
            return response()->json([
                'message'       => 'Présence déjà enregistrée.',
                'checked_in_at' => $registration->checked_in_at->format('Y-m-d H:i:s'),
            ], 409);
        }

        $registration->update(['checked_in_at' => now()]);

        return response()->json([
            'message'       => 'Présence enregistrée.',
            'user'          => ['id' => $registration->user->id, 'name' => $registration->user->name],
            'checked_in_at' => $registration->checked_in_at->format('Y-m-d H:i:s'),
        ]);
    }

    private function ownEvent(Request $request, $id): ?Event
    {
        return Event::where('id', $id)
            ->where('organizer_id', $request->user()->id)
            ->first();
    }
}