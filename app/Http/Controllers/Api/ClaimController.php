<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Claim;
use Illuminate\Http\Request;

class ClaimController extends Controller
{
    // POST /api/businesses/{id}/claims : revendiquer une fiche
    public function store(Request $request, $id)
    {
        $user = $request->user();

        // Seul un commerçant peut revendiquer une fiche
        if ($user->role !== 'merchant') {
            return response()->json(['message' => 'Seul un compte commerçant peut revendiquer une fiche.'], 403);
        }

        $business = Business::where('status', 'approved')->findOrFail($id);

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        // Une fiche qui a déjà un propriétaire ne peut plus être revendiquée
        if ($business->owner_id !== null) {
            return response()->json(['message' => 'Cette fiche a déjà un propriétaire.'], 409);
        }

        // Une seule revendication en attente par commerçant et par fiche
        $alreadyPending = Claim::where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            return response()->json(['message' => 'Vous avez déjà une revendication en attente pour cette fiche.'], 409);
        }

        $claim = new Claim($data);
        $claim->business_id = $business->id;   // fixés par le serveur
        $claim->user_id     = $user->id;
        $claim->status      = 'pending';       // validé ensuite par un administrateur

        $claim->save();

        return response()->json($claim, 201);
    }
}