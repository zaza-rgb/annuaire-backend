<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    // Crée les lignes de conversation_participants si elles n'existent pas (idempotent)
    private function addParticipants(Conversation $conversation, array $userIds): void
    {
        foreach ($userIds as $userId) {
            ConversationParticipant::firstOrCreate([
                'conversation_id' => $conversation->id,
                'user_id'         => $userId,
            ]);
        }
    }

    // Ligne 26 : mes conversations (commerce ou directes)
    public function index(Request $request)
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $me = $request->user();

        return Conversation::forUser($me)
            ->with(['business:id,name,city', 'user:id,name', 'lastMessage', 'participants.user:id,name'])
            ->withCount(['messages as unread_count' => function ($q) use ($me) {
                $q->whereNull('read_at')->where('user_id', '!=', $me->id);
            }])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20);
    }

    // Ligne 51 : nombre total de messages non lus (pastille de la barre de navigation)
    public function unreadCount(Request $request)
    {
        $me = $request->user();

        $unread = Message::query()
            ->whereNull('read_at')
            ->where('user_id', '!=', $me->id)
            ->whereHas('conversation', fn ($c) => $c->forUser($me));

        return response()->json([
            'unread_count'        => (clone $unread)->count(),
            'conversations_count' => (clone $unread)->distinct()->count('conversation_id'),
        ]);
    }

    // Ligne 27 : ouvrir (ou retrouver) ma conversation avec un commerce
    public function store(Request $request, $id)
    {
        $business = Business::where('status', 'approved')->find($id);
        if (!$business) {
            return response()->json(['message' => 'Commerce introuvable.'], 404);
        }

        if (!$business->owner_id) {
            return response()->json([
                'message' => 'Ce commerce n\'a pas de propriétaire : la messagerie est indisponible.',
            ], 422);
        }

        $me = $request->user();
        if ((int) $business->owner_id === (int) $me->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous écrire à vous-même.'], 403);
        }

        $conversation = Conversation::firstOrCreate([
            'user_id'     => $me->id,
            'business_id' => $business->id,
        ]);

        // Idempotent : répare aussi une conversation créée avant conversation_participants
        $this->addParticipants($conversation, [$me->id, $business->owner_id]);

        return response()->json(
            $conversation->load('business:id,name,city'),
            $conversation->wasRecentlyCreated ? 201 : 200
        );
    }

    // Ligne 47 : écrire à un autre utilisateur (conversation directe, sans commerce)
    public function storeDirect(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $me = $request->user();

        if ((int) $data['user_id'] === (int) $me->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous écrire à vous-même.'], 403);
        }

        $other = User::where('status', 'active')->find($data['user_id']);
        if (!$other) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        // Une seule conversation directe par paire d'utilisateurs, dans les deux sens
        $conversation = DB::transaction(function () use ($me, $other) {
            $existing = Conversation::where('type', 'direct')
                ->whereHas('participants', fn ($q) => $q->where('user_id', $me->id))
                ->whereHas('participants', fn ($q) => $q->where('user_id', $other->id))
                ->first();

            if ($existing) {
                return $existing;
            }

            $created = Conversation::create([
                'user_id'     => $me->id,
                'business_id' => null,
                'type'        => 'direct',
            ]);
            $this->addParticipants($created, [$me->id, $other->id]);

            return $created;
        });

        return response()->json(
            $conversation->load('participants.user:id,name'),
            $conversation->wasRecentlyCreated ? 201 : 200
        );
    }

    // Ligne 49 : bloquer le correspondant (POST) ou le débloquer (DELETE)
    public function block(Request $request, $id)
    {
        return $this->setBlocked($request, $id, true);
    }

    public function unblock(Request $request, $id)
    {
        return $this->setBlocked($request, $id, false);
    }

    // is_blocked est posé sur la ligne du correspondant (celui qui ne peut plus écrire).
    // Chacun n'agit donc que sur l'autre : un utilisateur bloqué ne peut pas se débloquer lui-même.
    private function setBlocked(Request $request, $id, bool $blocked)
    {
        $conversation = Conversation::with('business:id,owner_id')->find($id);
        if (!$conversation) {
            return response()->json(['message' => 'Conversation introuvable.'], 404);
        }

        $me = $request->user();
        if (!$conversation->isParticipant($me)) {
            return response()->json(['message' => 'Accès refusé à cette conversation.'], 403);
        }

        // Répare une conversation créée avant conversation_participants
        $conversation->ensureParticipants();

        $other = $conversation->participants()->where('user_id', '!=', $me->id)->first();
        if (!$other) {
            return response()->json(['message' => 'Aucun correspondant dans cette conversation.'], 422);
        }

        $other->update(['is_blocked' => $blocked]);

        return response()->json([
            'blocked' => $blocked,
            'user_id' => $other->user_id,
        ]);
    }
}
