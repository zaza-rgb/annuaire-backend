<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventReport;
use Illuminate\Http\Request;

class EventReportController extends Controller
{
    public function store(Request $request, $id)
    {
        $data = $request->validate([
            'reason'  => ['required', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        // Seuls les événements visibles publiquement peuvent être signalés
        $event = Event::where('status', 'approved')->find($id);
        if (! $event) {
            return response()->json(['message' => 'Événement introuvable.'], 404);
        }

        $user = $request->user();

        if ($event->organizer_id === $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas signaler votre propre événement.'], 403);
        }

        $alreadyOpen = EventReport::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->exists();
        if ($alreadyOpen) {
            return response()->json(['message' => 'Vous avez déjà signalé cet événement.'], 409);
        }

        $report = EventReport::create([
            'event_id' => $event->id,
            'user_id'  => $user->id,
            'reason'   => $data['reason'],
            'message'  => $data['message'] ?? null,
        ]);

        return response()->json($report->fresh(), 201);
    }
}