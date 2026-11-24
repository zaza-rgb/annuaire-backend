<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventConversationController extends Controller
{
    public function store(Request $request, $id)
    {
        $user = $request->user();

        $event = Event::where('status', 'approved')->find($id);
        if (!$event) {
            return response()->json(['message' => 'Événement introuvable.'], 404);
        }

        if ((int) $event->organizer_id === (int) $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous écrire à vous-même.'], 403);
        }

        $organizer = User::where('id', $event->organizer_id)
            ->where('status', 'active')
            ->first();
        if (!$organizer) {
            return response()->json(['message' => 'Organisateur introuvable.'], 404);
        }

        $conversation = Conversation::where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $organizer->id))
            ->first();

        $created = false;

        if (!$conversation) {
            $conversation = DB::transaction(function () use ($user, $organizer) {
                $conversation = Conversation::create([
                    'type'        => 'direct',
                    'business_id' => null,
                    'user_id'     => $user->id,
                ]);

                $conversation->participants()->createMany([
                    ['user_id' => $user->id],
                    ['user_id' => $organizer->id],
                ]);

                return $conversation;
            });
            $created = true;
        }

        $conversation->load('participants.user:id,name');

        return response()->json($conversation, $created ? 201 : 200);
    }
}