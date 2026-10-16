<?php

namespace App\Http\Controllers\Sec;

use App\Models\Sec\SecretaryDocument;
use App\Models\Sec\SecretaryMeeting;
use App\Models\Sec\SecretaryMemo;
use Inertia\Inertia;

class SecretaryDashboardController extends SecretaryController
{
    public function index()
    {
        $today = today()->toDateString();

        return Inertia::render('Dashboard/SEC/Dashboard', [
            'stats' => [
                'meetings_today' => SecretaryMeeting::whereDate('meeting_date', $today)
                    ->whereIn('status', ['scheduled', 'ongoing'])->count(),
                'meetings_week' => SecretaryMeeting::whereBetween('meeting_date', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'pending_documents' => SecretaryDocument::whereIn('status', ['logged', 'forwarded', 'in_progress'])->count(),
                'overdue_documents' => SecretaryDocument::whereNotNull('deadline')
                    ->whereDate('deadline', '<', $today)
                    ->whereNotIn('status', ['filed', 'closed'])->count(),
                'active_memos' => SecretaryMemo::where('status', 'published')->count(),
            ],
            'todaysMeetings' => SecretaryMeeting::whereDate('meeting_date', $today)
                ->orderBy('start_time')->take(5)->get(),
            'urgentDocuments' => SecretaryDocument::whereNotIn('status', ['filed', 'closed'])
                ->orderByRaw('deadline IS NULL, deadline ASC')->take(5)->get(),
            'latestMemos' => SecretaryMemo::where('status', 'published')
                ->latest('published_at')->take(5)->get(),
        ]);
    }
}
