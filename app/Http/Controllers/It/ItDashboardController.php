<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\It\ItAsset;
use App\Models\It\ItChange;
use App\Models\It\ItKnowledgeArticle;
use App\Models\It\ItSystem;
use App\Models\It\ItTicket;
use Inertia\Inertia;

class ItDashboardController extends Controller
{
    /**
     * Role-aware IT operations dashboard.
     * Managers get the full ops picture; staff get their queue + knowledge.
     */
    public function index()
    {
        $user = auth()->user();
        $isManager = $user->position === 'manager';

        $openTickets = ItTicket::open()->count();
        $breached = ItTicket::breached()->count();

        $stats = [
            'openTickets' => $openTickets,
            'breachedTickets' => $breached,
            'p1Open' => ItTicket::open()->where('priority', 'P1')->count(),
            'assetsInUse' => ItAsset::where('status', 'in_use')->count(),
            'assetsExpiring' => ItAsset::expiring()->count(),
            'assetsExpired' => ItAsset::expired()->count(),
            'systemsDown' => ItSystem::where('status', 'down')->count(),
            'systemsDegraded' => ItSystem::where('status', 'degraded')->count(),
            'pendingChanges' => ItChange::where('status', 'pending_approval')->count(),
            'knowledgeArticles' => ItKnowledgeArticle::published()->count(),
        ];

        if ($isManager) {
            return Inertia::render('Dashboard/IT/Manager/index', [
                'stats' => $stats,
                'recentTickets' => ItTicket::open()
                    ->with(['assignee:id,name', 'requester:id,name'])
                    ->orderByRaw("FIELD(priority, 'P1', 'P2', 'P3', 'P4')")
                    ->latest()
                    ->limit(8)
                    ->get(),
                'attentionSystems' => ItSystem::whereIn('status', ['down', 'degraded'])
                    ->latest('last_checked_at')
                    ->limit(5)
                    ->get(),
            ]);
        }

        return Inertia::render('Dashboard/IT/Employee/index', [
            'stats' => $stats,
            'myTickets' => ItTicket::open()
                ->where(function ($q) use ($user) {
                    $q->where('assignee_id', $user->id)
                        ->orWhere('requester_id', $user->id);
                })
                ->with('assignee:id,name')
                ->orderByRaw("FIELD(priority, 'P1', 'P2', 'P3', 'P4')")
                ->latest()
                ->limit(8)
                ->get(),
            'knowledge' => ItKnowledgeArticle::published()
                ->latest()
                ->limit(6)
                ->get(['id', 'title', 'category', 'audience', 'views']),
        ]);
    }
}
