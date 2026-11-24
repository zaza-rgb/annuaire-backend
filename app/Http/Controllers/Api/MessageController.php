<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    private function findFor(Request $request, $id): array
    {
        $conversation = Conversation::with('business:id,owner_id')->find($id);

        if (!$conversation) {
            return [null, response()->json(['message' => 'Conversation introuvable.'], 404)];
        }
        if (!$conversation->isParticipant($request->user())) {
            return [null, response()->json(['message' => 'Accès refusé à cette conversation.'], 403)];
        }

        return [$conversation, null];
    }

    // Ligne 28 : lire les messages (du plus récent au plus ancien)
    public function index(Request $request, $id)
    {
        [$conversation, $error] = $this->findFor($request, $id);
        if ($error) {
            return $error;
        }

        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $conversation->messages()->with('user:id,name');

        if (isset($data['after_id'])) {
            $query->where('id', '>', $data['after_id']);
        }

        return $query->orderByDesc('id')->paginate($data['per_page'] ?? 50);
    }

    // Ligne 29 : envoyer un message (ligne 48 : avec une photo facultative)
    public function store(Request $request, $id)
    {
        [$conversation, $error] = $this->findFor($request, $id);
        if ($error) {
            return $error;
        }

        // Ligne 49 : un participant bloqué ne peut plus écrire (il peut toujours lire)
        $blocked = $conversation->participants()
            ->where('user_id', $request->user()->id)
            ->where('is_blocked', true)
            ->exists();
        if ($blocked) {
            return response()->json([
                'message' => 'Vous ne pouvez plus envoyer de message dans cette conversation.',
            ], 403);
        }

        // Ligne 48 : content facultatif si une photo est jointe
        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:2000', 'required_without:photo'],
            'photo'   => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $imagePath = null;
        if ($request->hasFile('photo')) {
            $imagePath = $request->file('photo')->store('message-photos', 'public');
        }

        $message = $conversation->messages()->create([
            'user_id'    => $request->user()->id,
            'content'    => $data['content'] ?? null,
            'image_path' => $imagePath,
        ]);

        $conversation->touch();

        return response()->json($message->load('user:id,name'), 201);
    }

    // Ligne 30 : marquer comme lus les messages reçus
    public function markAsRead(Request $request, $id)
    {
        [$conversation, $error] = $this->findFor($request, $id);
        if ($error) {
            return $error;
        }

        $marked = $conversation->messages()
            ->whereNull('read_at')
            ->where('user_id', '!=', $request->user()->id)
            ->update(['read_at' => now()]);

        return response()->json(['marked' => $marked]);
    }
}