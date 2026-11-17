<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // POST /api/businesses/{id}/reviews : laisser un avis et une note
    public function store(Request $request, $id)
    {
        $business = Business::where('status', 'approved')->findOrFail($id);

        $data = $request->validate([
            'rating'  => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        // Un commerçant ne peut pas noter son propre commerce
        if ($business->owner_id !== null && (int) $business->owner_id === (int) $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas noter votre propre commerce.'], 403);
        }

        // Un seul avis par utilisateur et par commerce
        $alreadyReviewed = Review::where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyReviewed) {
            return response()->json(['message' => 'Vous avez déjà laissé un avis pour ce commerce.'], 409);
        }

        $review = new Review($data);
        $review->business_id = $business->id;   // fixés par le serveur
        $review->user_id     = $user->id;
        $review->status      = 'pending';       // validé ensuite par un administrateur
        $review->save();

        return response()->json($review, 201);
    }

    // PATCH /api/reviews/{id}/reply : le commerçant répond à un avis
    public function reply(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        // Un avis non validé n'est pas visible : on ne peut pas y répondre
        if ($review->status !== 'approved') {
            return response()->json(['message' => 'Avis introuvable.'], 404);
        }

        // Seul le propriétaire du commerce concerné peut répondre
        $ownerId = $review->business()->value('owner_id');
        if ((int) $ownerId !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Seul le propriétaire du commerce peut répondre à cet avis.',
            ], 403);
        }

        $data = $request->validate([
            'reply' => ['required', 'string', 'max:2000'],
        ]);

        $review->merchant_reply = $data['reply'];
        $review->replied_at = now();
        $review->save();

        return response()->json($review);
    }
}