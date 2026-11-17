<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // POST /api/businesses/{id}/reports : signaler une fiche incorrecte
    public function store(Request $request, $id)
    {
        $business = Business::where('status', 'approved')->findOrFail($id);

        $data = $request->validate([
            'reason'  => ['required', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        // Un seul signalement ouvert par utilisateur et par commerce
        $alreadyOpen = Report::where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->exists();

        if ($alreadyOpen) {
            return response()->json(['message' => 'Vous avez déjà signalé ce commerce : votre signalement est en cours de traitement.'], 409);
        }

        $report = new Report($data);
        $report->business_id = $business->id;   // fixés par le serveur
        $report->user_id     = $user->id;
        $report->status      = 'open';          // traité ensuite par un administrateur
        $report->save();

        return response()->json($report, 201);
    }
}