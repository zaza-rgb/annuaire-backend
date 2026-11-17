<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status'   => ['nullable', 'in:pending,approved,rejected'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status  = $data['status'] ?? 'pending';
        $perPage = $data['per_page'] ?? 20;

        return Photo::with(['business:id,name,city', 'user:id,name,email'])
            ->where('status', $status)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $photo = Photo::find($id);
        if (!$photo) {
            return response()->json(['message' => 'Photo introuvable.'], 404);
        }

        $photo->status = $data['status'];
        $photo->save();

        return $photo->load(['business:id,name,city', 'user:id,name,email']);
    }
}