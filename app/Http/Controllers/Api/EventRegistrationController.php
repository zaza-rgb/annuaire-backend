<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventRegistrationController extends Controller
{
    // Mes inscriptions actives, avec le code d'entrée.
    public function index(Request $request)
    {
        $registrations = EventRegistration::with('event')
            ->where('user_id', $request->user()->id)
            ->where('status', 'registered')
            ->get()
            ->sortBy(fn ($r) => $r->event->starts_at)
            ->values()
            ->map(fn ($r) => $this->present($r));

        return response()->json($registrations);
    }

    public function store(Request $request, $id)
    {
        $user = $request->user();

        // Transaction + verrou sur l'événement : deux inscriptions simultanées
        // ne peuvent pas dépasser le nombre de places.
        return DB::transaction(function () use ($id, $user) {
            $event = Event::where('id', $id)->where('status', 'approved')->lockForUpdate()->first();

            if (!$event) {
                return response()->json(['message' => 'Événement introuvable.'], 404);
            }

            if ($event->ends_at->isPast()) {
                return response()->json(['message' => 'Cet événement est terminé.'], 409);
            }

            $registration = EventRegistration::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->first();

            if ($registration && $registration->status === 'registered') {
                return response()->json(['message' => 'Vous êtes déjà inscrit.'], 409);
            }

            $taken = EventRegistration::where('event_id', $event->id)
                ->where('status', 'registered')
                ->count();

            if ($taken >= $event->capacity) {
                return response()->json(['message' => 'Événement complet.'], 409);
            }

            if ($registration) {
                // Réinscription après annulation : on garde le même code d'entrée.
                $registration->update(['status' => 'registered', 'checked_in_at' => null]);
            } else {
                $registration = EventRegistration::create([
                    'event_id'   => $event->id,
                    'user_id'    => $user->id,
                    'entry_code' => $this->newCode(),
                    'status'     => 'registered',
                ]);
            }

            return response()->json($this->present($registration->load('event')), 201);
        });
    }

    public function destroy(Request $request, $id)
    {
        $registration = EventRegistration::where('event_id', $id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'registered')
            ->first();

        if (!$registration) {
            return response()->json(['message' => 'Inscription introuvable.'], 404);
        }

        if ($registration->checked_in_at) {
            return response()->json(['message' => 'Votre présence est déjà enregistrée.'], 409);
        }

        $registration->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Inscription annulée.']);
    }

    // Code lisible : sans 0/O ni 1/I, pour être saisi à la main si le QR ne passe pas.
    private function newCode(): string
    {
        do {
            $code = '';
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (EventRegistration::where('entry_code', $code)->exists());

        return $code;
    }

    private function present(EventRegistration $registration): array
    {
        $event = $registration->event;

        return [
            'event_id'      => $registration->event_id,
            'entry_code'    => $registration->entry_code,
            'status'        => $registration->status,
            'checked_in_at' => $registration->checked_in_at?->format('Y-m-d H:i:s'),
            'event'         => [
                'id'        => $event->id,
                'title'     => $event->title,
                'city'      => $event->city,
                'address'   => $event->address,
                'starts_at' => $event->starts_at->format('Y-m-d H:i:s'),
                'ends_at'   => $event->ends_at->format('Y-m-d H:i:s'),
                'status'    => $event->status,
            ],
        ];
    }
}