<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Claim;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventReport;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\Photo;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;

class StatsController extends Controller
{
    public function index()
    {
        $count = fn ($model, $column) => $model::selectRaw("$column as k, count(*) as n")
            ->groupBy($column)->pluck('n', 'k');

        $users = $count(User::class, 'role');
        $businesses = $count(Business::class, 'status');
        $events = $count(Event::class, 'status');

        return [
            'users' => [
                'total' => User::count(),
                'by_role' => [
                    'user' => $users['user'] ?? 0,
                    'merchant' => $users['merchant'] ?? 0,
                    'organizer' => $users['organizer'] ?? 0,
                    'admin' => $users['admin'] ?? 0,
                ],
                'suspended' => User::where('status', 'suspended')->count(),
                'pending' => User::where('status', 'pending')->count(),
            ],
            'businesses' => [
                'total' => Business::count(),
                'pending' => $businesses['pending'] ?? 0,
                'approved' => $businesses['approved'] ?? 0,
                'rejected' => $businesses['rejected'] ?? 0,
            ],
            'events' => [
                'total' => Event::count(),
                'pending' => $events['pending'] ?? 0,
                'approved' => $events['approved'] ?? 0,
                'rejected' => $events['rejected'] ?? 0,
                'cancelled' => $events['cancelled'] ?? 0,
            ],
            'registrations' => [
                'active' => EventRegistration::where('status', 'registered')->count(),
                'checked_in' => EventRegistration::where('status', 'registered')
                    ->whereNotNull('checked_in_at')->count(),
            ],
            'pending' => [
                'reviews' => Review::where('status', 'pending')->count(),
                'photos' => Photo::where('status', 'pending')->count(),
                'claims' => Claim::where('status', 'pending')->count(),
            ],
            'reports_open' => Report::where('status', 'open')->count(),
            'event_reports_open' => EventReport::where('status', 'open')->count(),
            'message_reports_open' => MessageReport::where('status', 'open')->count(),
            'conversations' => Conversation::count(),
            'messages' => Message::count(),
        ];
    }
}