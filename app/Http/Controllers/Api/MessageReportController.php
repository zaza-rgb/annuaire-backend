<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use Illuminate\Http\Request;

class MessageReportController extends Controller
{
    /**
     * POST /api/messages/{id}/report
     * Signale un message (ligne 50 de la checklist, section 49).
     */
    public function store(Request $request, $id)
    {
        $user = $request->user();

        $message = Message::find($id);
        if (!$message) {
            return response()->json(['message' => 'Message introuvable.'], 404);
        }

        $conversation = Conversation::with('business')->find($message->conversation_id);
        if (!$conversation) {
            return response()->json(['message' => 'Conversation introuvable.'], 404);
        }

        // Participant : ligne dans conversation_participants, ou ancienne règle
        // (client ou propriétaire du commerce) pour les conversations anciennes.
        $isParticipant = $conversation->participants()->where('user_id', $user->id)->exists()
            || (int) $conversation->user_id === (int) $user->id
            || ($conversation->business && (int) $conversation->business->owner_id === (int) $user->id);

        if (!$isParticipant) {
            return response()->json(['message' => 'Accès refusé à cette conversation.'], 403);
        }

        if ((int) $message->user_id === (int) $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas signaler votre propre message.'], 403);
        }

        $data = $request->validate([
            'reason' => 'required|string|max:100',
        ]);

        $already = MessageReport::where('message_id', $message->id)
            ->where('reporter_id', $user->id)
            ->exists();
        if ($already) {
            return response()->json(['message' => 'Vous avez déjà signalé ce message.'], 409);
        }

        $report = MessageReport::create([
            'message_id'  => $message->id,
            'reporter_id' => $user->id,
            'reason'      => $data['reason'],
        ]);

        return response()->json($report->fresh(), 201);
    }
}