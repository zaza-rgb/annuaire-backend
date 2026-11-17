<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Claim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClaimController extends Controller
{
    // GET /api/admin/claims?status=pending : revendications à examiner
    public function index(Request $request)
    {
        $data = $request->validate([
            'status'   => ['nullable', 'in:pending,approved,rejected'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Claim::with([
                'business:id,name,city,owner_id',
                'user:id,name,email',
            ])
            ->where('status', $data['status'] ?? 'pending')
            ->oldest();   // les plus anciennes demandes en premier

        return response()->json($query->paginate($data['per_page'] ?? 20));
    }

    // PATCH /api/admin/claims/{id} : accepter ou refuser une revendication
    public function update(Request $request, $id)
    {
        $claim = Claim::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

        if ($claim->status !== 'pending') {
            return response()->json(['message' => 'Cette revendication a déjà été traitée.'], 409);
        }

        if ($data['status'] === 'approved') {
            $business = Business::findOrFail($claim->business_id);

            if ($business->owner_id !== null) {
                return response()->json(['message' => 'Cette fiche a déjà un propriétaire.'], 409);
            }

            DB::transaction(function () use ($claim, $business) {
                $business->owner_id = $claim->user_id;
                $business->save();

                $claim->status = 'approved';
                $claim->save();

                // Les autres demandes en attente sur cette fiche sont refusées
                Claim::where('business_id', $business->id)
                    ->where('id', '!=', $claim->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'rejected']);
            });
        } else {
            $claim->status = 'rejected';
            $claim->save();
        }

        return response()->json($claim);
    }
}