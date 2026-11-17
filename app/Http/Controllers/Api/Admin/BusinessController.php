<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    // GET /api/admin/businesses?status=pending : fiches à examiner
    public function index(Request $request)
    {
        $data = $request->validate([
            'status'   => ['nullable', 'in:pending,approved,rejected'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Business::with([
                'category',
                'country',
                'owner:id,name,email',
                'proposer:id,name,email',
            ])
            ->where('status', $data['status'] ?? 'pending')
            ->oldest();   // les plus anciennes demandes en premier

        return response()->json($query->paginate($data['per_page'] ?? 20));
    }

    // PATCH /api/admin/businesses/{id}/status : valider ou refuser une fiche
    public function updateStatus(Request $request, $id)
    {
        $business = Business::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $business->status = $data['status'];
        $business->save();

        return response()->json($business);
    }
}