<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    private const SUSPENDED = 'suspended';
    private const FIELDS = ['id', 'name', 'email', 'phone', 'role', 'status', 'language_id', 'created_at'];

    public function index(Request $request)
    {
        $data = $request->validate([
            'status'   => 'sometimes|in:active,pending,' . self::SUSPENDED,
            'role'     => 'sometimes|in:user,merchant,organizer,admin',
            'q'        => 'sometimes|string|max:100',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = User::select(self::FIELDS);

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (isset($data['role'])) {
            $query->where('role', $data['role']);
        }

        if (isset($data['q'])) {
            $q = $data['q'];
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }

        return $query->orderByDesc('id')->paginate($data['per_page'] ?? 20);
    }

    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:active,' . self::SUSPENDED,
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Compte introuvable.'], 404);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas modifier votre propre compte.'], 403);
        }

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Un compte administrateur ne peut pas être suspendu.'], 403);
        }

        $user->status = $data['status'];
        $user->save();

        if ($data['status'] === self::SUSPENDED) {
            $user->tokens()->delete();
        }

        return $user->only(self::FIELDS);
    }
}