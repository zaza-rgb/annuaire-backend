<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    // Liste de tous les événements, du plus récent au plus ancien.
    public function index(Request $request)
    {
        $data = $request->validate([
            'status'   => 'sometimes|in:pending,approved,rejected,cancelled',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Event::with(['category', 'languages', 'organizer:id,name,email']);

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20);
    }

    // Valider ou refuser un événement.
    public function updateStatus(Request $request, $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Événement introuvable.'], 404);
        }

        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        if ($event->status === 'cancelled') {
            return response()->json(['message' => 'Un événement annulé ne peut pas être modifié.'], 409);
        }

        $event->update(['status' => $data['status']]);

        return response()->json($event->load(['category', 'languages', 'organizer:id,name,email']));
    }
}