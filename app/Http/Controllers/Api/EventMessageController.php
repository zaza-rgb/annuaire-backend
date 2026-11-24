<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventMessageController extends Controller
{
    /**
     * POST /api/events/{id}/broadcast
     * Écrit aux inscrits d'un événement (ligne 69, section 53).
     */
    public function store(Request $request, $id)
    {
        $user = $request->user();

        $event = Event::find($id);
        if (!$event || (int) $event->organizer_id !== (int) $user->id) {
            return response()->json(['message' => 'Événement introuvable.'], 404);
        }

        // Approuvé ou annulé seulement (on peut prévenir les inscrits d'une annulation).
        if (!in_array($event->status, ['approved', 'cancelled'], true)) {
            return response()->json(['message' => 'Cet événement n\'est pas publié.'], 409);
        }

        $data = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $registeredIds = EventRegistration::where('event_id', $event->id)
            ->where('status', 'registered')
            ->pluck('user_id');

        $recipientIds = User::whereIn('id', $registeredIds)
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->pluck('id');

        $sent = 0;
        $skipped = 0;

        DB::transaction(function () use ($recipientIds, $user, $data, &$sent, &$skipped) {
            foreach ($recipientIds as $recipientId) {
                $conversation = Conversation::where('type', 'direct')
                    ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
                    ->whereHas('participants', fn ($q) => $q->where('user_id', $recipientId))
                    ->first();

                if ($conversation) {
                    // L'inscrit a bloqué l'organisateur : la ligne de l'organisateur est bloquée.
                    $blocked = $conversation->participants()
                        ->where('user_id', $user->id)
                        ->where('is_blocked', true)
                        ->exists();
                    if ($blocked) {
                        $skipped++;
                        continue;
                    }
                } else {
                    $conversation = Conversation::create([
                        'type'        => 'direct',
                        'business_id' => null,
                        'user_id'     => $user->id,
                    ]);
                    $conversation->participants()->createMany([
                        ['user_id' => $user->id],
                        ['user_id' => $recipientId],
                    ]);
                }

                (new Message)->forceFill([
                    'conversation_id' => $conversation->id,
                    'user_id'         => $user->id,
                    'content'         => $data['content'],
                ])->save();

                $conversation->touch();
                $sent++;
            }
        });

        return response()->json([
            'event_id'              => $event->id,
            'sent_count'            => $sent,
            'skipped_blocked_count' => $skipped,
        ], $sent > 0 ? 201 : 200);
    }
}