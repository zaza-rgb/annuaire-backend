<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status'   => 'sometimes|in:open,resolved,dismissed',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        return Report::with([
                'business:id,name,city,status',
                'user:id,name,email',
            ])
            ->where('status', $data['status'] ?? 'open')
            ->orderBy('created_at')
            ->paginate($data['per_page'] ?? 20);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:resolved,dismissed',
        ]);

        $report = Report::find($id);

        if (!$report) {
            return response()->json(['message' => 'Signalement introuvable.'], 404);
        }

        if ($report->status !== 'open') {
            return response()->json(['message' => 'Ce signalement a déjà été traité.'], 409);
        }

        $report->status = $data['status'];
        $report->save();

        return $report->load([
            'business:id,name,city,status',
            'user:id,name,email',
        ]);
    }
}