<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    // PATCH /api/me : modifier son profil
    public function update(Request $request)
    {
        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'phone'       => ['sometimes', 'nullable', 'string', 'max:20'],
            'language_id' => ['sometimes', 'integer', 'exists:languages,id'],
        ]);

        $user = $request->user();
        $user->update($data);

        return response()->json($user->fresh());
    }
}