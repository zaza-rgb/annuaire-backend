<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Photo;
use App\Models\Review;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function store(Request $request, $id)
    {
        $business = Business::where('status', 'approved')->find($id);
        if (!$business) {
            return response()->json(['message' => 'Commerce introuvable.'], 404);
        }

        $data = $request->validate([
            'photo'     => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'review_id' => ['nullable', 'integer'],
        ]);

        $reviewId = null;
        if (!empty($data['review_id'])) {
            $review = Review::where('id', $data['review_id'])
                ->where('business_id', $business->id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$review) {
                return response()->json([
                    'message' => 'Cet avis n\'existe pas ou n\'est pas le vôtre.',
                    'errors'  => ['review_id' => ['Avis invalide pour ce commerce.']],
                ], 422);
            }
            $reviewId = $review->id;
        }

        $path = $request->file('photo')->store('photos', 'public');

        $photo = Photo::create([
            'business_id' => $business->id,
            'review_id'   => $reviewId,
            'user_id'     => $request->user()->id,
            'file_path'   => $path,
            'status'      => 'pending',
        ]);

        return response()->json($photo, 201);
    }
}