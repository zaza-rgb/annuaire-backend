<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // GET /api/admin/reviews?status=pending : avis à modérer
    public function index(Request $request)
    {
        $data = $request->validate([
            'status'   => ['nullable', 'in:pending,approved,rejected'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Review::with([
                'business:id,name,city',
                'user:id,name,email',
            ])
            ->where('status', $data['status'] ?? 'pending')
            ->oldest();   // les plus anciens avis en premier

        return response()->json($query->paginate($data['per_page'] ?? 20));
    }

    // PATCH /api/admin/reviews/{id}/status : valider ou refuser un avis
    public function updateStatus(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $review->status = $data['status'];
        $review->save();

        return response()->json($review);
    }
}