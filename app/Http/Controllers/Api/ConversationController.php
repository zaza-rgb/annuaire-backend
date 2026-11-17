<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    // Ligne 26 : mes conversations (comme client ou comme propriétaire)
    public function index(Request $request)
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $me = $request->user();

        return Conversation::query()
            ->where(function ($q) use ($me) {
                $q->where('user_id', $me->id)
                  ->orWhereHas('business', fn ($b) => $b->where('owner_id', $me->id));
            })
            ->with(['business:id,name,city', 'user:id,name', 'lastMessage'])
            ->withCount(['messages as unread_count' => function ($q) use ($me) {
                $q->whereNull('read_at')->where('user_id', '!=', $me->id);
            }])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20);
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

        return response()->json(
            $conversation->load('business:id,name,city'),
            $conversation->wasRecentlyCreated ? 201 : 200
        );
    }
}