<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use Illuminate\Http\Request;

class MessageReportController extends Controller
{
    /** GET /api/admin/message-reports (ligne 74, section 50) */
    public function index(Request $request)
    {
        $data = $request->validate([
            'status'   => 'nullable|in:open,resolved,dismissed',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $status  = $data['status'] ?? 'open';
        $perPage = $data['per_page'] ?? 20;

        $reports = MessageReport::where('status', $status)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage);

        $messages = Message::whereIn('id', $reports->pluck('message_id'))->get()->keyBy('id');
        $userIds  = $reports->pluck('reporter_id')->merge($messages->pluck('user_id'))->unique();
        $users    = User::whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');

        $person = fn ($id) => $users->has($id) ? ['id' => $id, 'name' => $users[$id]->name] : null;
        $slim   = fn ($m) => [
            'id'         => $m->id,
            'user_id'    => $m->user_id,
            'content'    => $m->content,
            'image_path' => $m->image_path,
            'created_at' => $m->created_at,
        ];

        $reports->through(function ($report) use ($messages, $person, $slim) {
            $m = $messages->get($report->message_id);
            $context = ['before' => [], 'after' => []];
            $message = null;

            if ($m) {
                $message = [
                    'id'              => $m->id,
                    'conversation_id' => $m->conversation_id,
                    'user'            => $person($m->user_id),
                    'content'         => $m->content,
                    'image_path'      => $m->image_path,
                    'created_at'      => $m->created_at,
                ];
                $context['before'] = Message::where('conversation_id', $m->conversation_id)
                    ->where('id', '<', $m->id)->orderByDesc('id')->limit(2)->get()
                    ->reverse()->values()->map($slim)->all();
                $context['after'] = Message::where('conversation_id', $m->conversation_id)
                    ->where('id', '>', $m->id)->orderBy('id')->limit(2)->get()
                    ->map($slim)->all();
            }

            return [
                'id'         => $report->id,
                'reason'     => $report->reason,
                'status'     => $report->status,
                'created_at' => $report->created_at,
                'reporter'   => $person($report->reporter_id),
                'message'    => $message,
                'context'    => $context,
            ];
        });

        return response()->json($reports);
    }

    /** PATCH /api/admin/message-reports/{id} */
    public function update(Request $request, $id)
    {
        $report = MessageReport::find($id);
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