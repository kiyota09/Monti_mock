<?php

namespace App\Http\Controllers\Vp;

use App\Http\Controllers\Controller;
use App\Models\Man\BoilerLog;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Man\Package;
use App\Models\Man\SafetyIncident;
use App\Models\Man\ShiftHandover;
use App\Models\Ord\SalesOrder;
use App\Models\Vp\ExecutiveDirective;
use App\Models\Vp\VpBulletin;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VpBriefsController extends Controller
{
    /**
     * Downtime & Maintenance Board: every fault, its age, and MTTR by type.
     */
    public function downtime()
    {
        $open = MachineReport::with(['machine', 'reporter'])
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'machine_no' => $r->machine?->machine_no ?? '—',
                'machine_type' => $r->machine?->type ?? '—',
                'issue' => $r->issue,
                'reporter' => $r->reporter?->name ?? '—',
                'age_hours' => round($r->created_at->diffInHours(now()), 1),
                'created_at' => $r->created_at,
            ]);

        $mttr = MachineReport::with('machine')
            ->where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->latest()
            ->take(200)
            ->get()
            ->groupBy(fn ($r) => $r->machine?->type ?? 'unknown')
            ->map(fn ($rows, $type) => [
                'type' => $type,
                'count' => $rows->count(),
                'avg_hours' => round($rows->avg(fn ($r) => $r->created_at->diffInHours($r->resolved_at)), 1),
            ])->values();

        return Inertia::render('Dashboard/VP/Downtime', [
            'openReports' => $open,
            'mttr' => $mttr,
        ]);
    }

    /**
     * Shift handovers: latest per department + recent history.
     */
    public function handovers()
    {
        $departments = ['knitting', 'dyeing', 'finishing', 'maintenance', 'boiler'];
        $latest = [];
        foreach ($departments as $dept) {
            $latest[$dept] = ShiftHandover::with('author:id,name')
                ->where('department', $dept)
                ->latest()
                ->first();
        }

        return Inertia::render('Dashboard/VP/Handovers', [
            'latest' => $latest,
            'history' => ShiftHandover::with('author:id,name')
                ->latest()->take(30)->get(),
        ]);
    }

    /**
     * Production Plan vs Actual: open job orders against delivered output.
     */
    public function plan()
    {
        $delivered = Package::with('fabric')
            ->where('status', 'delivered')
            ->get()
            ->groupBy(fn ($p) => $p->fabric?->sales_order_id)
            ->map(fn ($rows) => $rows->sum('quantity'));

        $orders = SalesOrder::whereIn('status', ['in_production', 'pending'])
            ->orderBy('expected_ship_date')
            ->take(50)
            ->get()
            ->map(function ($o) use ($delivered) {
                $done = (float) ($delivered[$o->id] ?? 0);
                $total = (float) ($o->quantity ?? 0);
                return [
                    'id' => $o->id,
                    'jo_number' => $o->jo_number,
                    'color' => $o->color,
                    'quantity' => $total,
                    'delivered' => $done,
                    'progress' => $total > 0 ? round(min(100, $done / $total * 100), 1) : 0,
                    'status' => $o->status,
                    'expected_ship_date' => $o->expected_ship_date,
                ];
            });

        return Inertia::render('Dashboard/VP/Plan', [
            'orders' => $orders,
        ]);
    }

    /**
     * Utilities & Boiler Brief: fuel, steam hours, blowdown compliance.
     */
    public function utilities()
    {
        $logs = BoilerLog::where('processed_at', '>=', now()->subDays(30))->get();

        $byDay = $logs->groupBy(fn ($l) => $l->processed_at->format('Y-m-d'))
            ->map(fn ($rows, $day) => [
                'day' => $day,
                'fuel' => round($rows->sum('fuel_used'), 1),
                'hours' => round($rows->sum('operating_hours'), 1),
                'avg_pressure' => round($rows->avg('steam_pressure'), 1),
            ])->sortKeys()->values();

        return Inertia::render('Dashboard/VP/Utilities', [
            'totals' => [
                'fuel_30d' => round($logs->sum('fuel_used'), 1),
                'hours_30d' => round($logs->sum('operating_hours'), 1),
                'blowdown_rate' => $logs->count() > 0
                    ? round($logs->where('blowdown_done', true)->count() / $logs->count() * 100, 1) : 0,
                'logs_count' => $logs->count(),
            ],
            'byDay' => $byDay,
            'boilers' => Machine::where('type', 'boiler')->get(['id', 'machine_no', 'status']),
        ]);
    }

    /**
     * Operational bulletins (VP broadcasts to the floor).
     */
    public function bulletins()
    {
        return Inertia::render('Dashboard/VP/Bulletins', [
            'bulletins' => VpBulletin::with('creator:id,name')->latest()->take(50)->get(),
        ]);
    }

    public function storeBulletin(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'audience' => 'required|in:company,knitting,dyeing,finishing,maintenance,boiler',
            'priority' => 'required|in:low,normal,high,urgent',
            'expires_at' => 'nullable|date|after_or_equal:today',
        ]);

        VpBulletin::create([...$validated, 'created_by' => auth()->id()]);

        return back()->with('message', 'Bulletin published to the floor.');
    }

    public function destroyBulletin(VpBulletin $bulletin)
    {
        $bulletin->delete();

        return back()->with('message', 'Bulletin removed.');
    }
}
