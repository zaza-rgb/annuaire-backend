<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'language_id' => 'required|exists:languages,id',
            'account_type' => 'required|in:user,merchant,organizer',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'language_id' => $data['language_id'],
        ]);

        // Le rôle et le statut sont fixés ici, par le serveur : jamais « admin » depuis un formulaire.
        $user->role = $data['account_type'];
        $user->status = $data['account_type'] === 'organizer' ? 'pending' : 'active';
        $user->save();
        $user->refresh();

        // Un organisateur doit être validé par l'administrateur avant de se connecter.
        if ($user->status === 'pending') {
            return response()->json([
                'message' => 'Compte créé. Il sera activé après validation par un administrateur.',
                'user' => $this->userData($user),
            ], 201);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['user' => $this->userData($user), 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        if ($user->status === 'pending') {
            return response()->json(['message' => 'Compte en attente de validation.'], 403);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Compte suspendu.'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['user' => $this->userData($user), 'token' => $token]);
    }

    public function me(Request $request)
    {
        return response()->json($this->userData($request->user()));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    private function userData(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'phone', 'role', 'status', 'language_id']);
    }
}