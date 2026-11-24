<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\User;
use Illuminate\Http\Request;

class EventReportController extends Controller
{
    /** GET /api/admin/event-reports (ligne 83, section 51) */
    public function index(Request $request)
    {
        $data = $request->validate([
            'status'   => 'nullable|in:open,resolved,dismissed',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $status  = $data['status'] ?? 'open';
        $perPage = $data['per_page'] ?? 20;

        $reports = EventReport::where('status', $status)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage);

        $events = Event::whereIn('id', $reports->pluck('event_id'))
            ->get(['id', 'title', 'city', 'starts_at', 'status', 'organizer_id'])->keyBy('id');
        $users = User::whereIn('id', $reports->pluck('user_id'))->get(['id', 'name'])->keyBy('id');

        $reports->through(function ($report) use ($events, $users) {
            $e = $events->get($report->event_id);
            $u = $users->get($report->user_id);

            return [
                'id'         => $report->id,
                'reason'     => $report->reason,
                'message'    => $report->message,
                'status'     => $report->status,
                'created_at' => $report->created_at,
                'reporter'   => $u ? ['id' => $u->id, 'name' => $u->name] : null,
                'event'      => $e ? [
                    'id'           => $e->id,
                    'title'        => $e->title,
                    'city'         => $e->city,
                    'starts_at'    => $e->starts_at,
                    'status'       => $e->status,
                    'organizer_id' => $e->organizer_id,
                ] : null,
            ];
        });

        return response()->json($reports);
    }

    /** PATCH /api/admin/event-reports/{id} */
    public function update(Request $request, $id)
    {
        $report = EventReport::find($id);
        if (!$report) {
            return response()->json(['message' => 'Signalement introuvable.'], 404);
        }

        $data = $request->validate([
            'status' => 'required|in:resolved,dismissed',
        ]);

        if ($report->status !== 'open') {
            return response()->json(['message' => 'Signalement déjà traité.'], 409);
        }

        $report->status = $data['status'];
        $report->save();

        return response()->json($report->fresh());
    }
}