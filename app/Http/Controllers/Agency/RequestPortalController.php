<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\ManpowerProposal;
use App\Models\Hrm\ManpowerRequest;
use App\Models\Hrm\Worker;
use App\Support\NotifiesHr;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Agency portal: requests addressed to the agency (read-only) + proposing
 * workers on open requests. Proposals add a "Proposed" step before HR
 * assigns. The agency can never edit/cancel a request.
 */
class RequestPortalController extends Controller
{
    private function agencyId(Request $request): int
    {
        return (int) $request->user('agency')->agency_id;
    }

    public function index(Request $request)
    {
        $agencyId = $this->agencyId($request);
        $q = ManpowerRequest::where('agency_id', $agencyId)->withCount([
            'activeAssignments as filled_count',
        ]);

        if ($s = $request->get('search')) {
            $q->where('code', 'like', "%{$s}%");
        }
        if (($status = $request->get('status')) && $status !== 'All') {
            // Effective-status filter in memory (volumes are agency-scale).
            $all = $q->orderByDesc('id')->get()->filter(fn ($r) => $r->effective_status === $status)->values();
        } else {
            $all = $q->orderByDesc('id')->get();
        }

        $total = $all->count();
        $perPage = 15;
        $pageNum = max(1, min((int) $request->get('page', 1), max(1, (int) ceil($total / $perPage))));

        return Inertia::render('Agency/Requests/Index', [
            'requests' => $all->slice(($pageNum - 1) * $perPage, $perPage)->values()->map(fn ($r) => [
                'id' => $r->id, 'code' => $r->code, 'department' => $r->department,
                'position_skill' => $r->position_skill, 'needed' => (int) $r->headcount,
                'filled' => (int) $r->filled_count,
                'start_date' => optional($r->start_date)->toDateString(),
                'status' => $r->effective_status,
            ])->all(),
            'pagination' => [
                'current_page' => $pageNum,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Request $request, ManpowerRequest $manpowerRequest)
    {
        abort_unless((int) $manpowerRequest->agency_id === $this->agencyId($request), 404);
        $manpowerRequest->loadMissing(['activeAssignments.worker:id,code,name', 'agency:id,name']);

        $mine = ManpowerProposal::with('worker:id,code,name,position_skill')
            ->where('request_id', $manpowerRequest->id)
            ->orderByDesc('id')->get()->map(fn ($p) => [
                'id' => $p->id,
                'worker_code' => $p->worker?->code,
                'worker_name' => $p->worker?->name,
                'position_skill' => $p->worker?->position_skill,
                'status' => $p->status,
                'proposed_at' => optional($p->created_at)->format('M d, Y'),
                'review_reason' => $p->review_reason,
            ])->values()->all();

        $pickable = [];
        if ($manpowerRequest->status === ManpowerRequest::STATUS_OPEN
            && $manpowerRequest->filled_count < max(1, (int) $manpowerRequest->headcount)) {
            $pickable = Worker::where('agency_id', $this->agencyId($request))
                ->where('status', Worker::STATUS_AVAILABLE)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('manpower_assignments as ma')
                    ->whereColumn('ma.worker_id', 'workers.id')->whereNull('ma.released_at'))
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('manpower_proposals as mp')
                    ->whereColumn('mp.worker_id', 'workers.id')
                    ->where('mp.request_id', $manpowerRequest->id)
                    ->where('mp.status', ManpowerProposal::STATUS_PROPOSED))
                ->orderBy('name')->get(['id', 'code', 'name', 'position_skill', 'clearance_expiry'])
                ->filter(fn ($w) => ! $w->clearance_expired)
                ->map(fn ($w) => ['id' => $w->id, 'code' => $w->code, 'name' => $w->name, 'position_skill' => $w->position_skill])
                ->values()->all();
        }

        return Inertia::render('Agency/Requests/Show', [
            'request' => [
                'id' => $manpowerRequest->id, 'code' => $manpowerRequest->code,
                'department' => $manpowerRequest->department,
                'position_skill' => $manpowerRequest->position_skill,
                'agency' => $manpowerRequest->agency?->name,
                'needed' => (int) $manpowerRequest->headcount,
                'filled' => (int) $manpowerRequest->filled_count,
                'start_date' => optional($manpowerRequest->start_date)->toDateString(),
                'end_date' => optional($manpowerRequest->end_date)->toDateString(),
                'shift' => $manpowerRequest->shift, 'notes' => $manpowerRequest->notes,
                'status' => $manpowerRequest->effective_status,
                'stored_status' => $manpowerRequest->status,
                'accepted' => $manpowerRequest->activeAssignments->map(fn ($a) => [
                    'worker_code' => $a->worker?->code, 'worker_name' => $a->worker?->name,
                ])->values()->all(),
            ],
            'proposals' => $mine,
            'pickable_workers' => $pickable,
        ]);
    }

    public function propose(Request $request, ManpowerRequest $manpowerRequest)
    {
        abort_unless((int) $manpowerRequest->agency_id === $this->agencyId($request), 404);

        $data = $request->validate(['worker_id' => 'required|exists:workers,id']);
        $worker = Worker::findOrFail($data['worker_id']);
        $me = $request->user('agency');

        if ($manpowerRequest->status !== ManpowerRequest::STATUS_OPEN) {
            throw ValidationException::withMessages(['worker_id' => 'Workers can only be proposed on Open requests.']);
        }
        if ($manpowerRequest->filled_count >= max(1, (int) $manpowerRequest->headcount)) {
            throw ValidationException::withMessages(['worker_id' => 'This request is already filled.']);
        }
        if ((int) $worker->agency_id !== (int) $manpowerRequest->agency_id) {
            abort(404);
        }
        if ($worker->status !== Worker::STATUS_AVAILABLE) {
            throw ValidationException::withMessages(['worker_id' => 'Only Available workers can be proposed.']);
        }
        if ($worker->activeAssignment()->exists()) {
            throw ValidationException::withMessages(['worker_id' => 'This worker is already on another active request.']);
        }
        if ($worker->clearance_expired) {
            throw ValidationException::withMessages(['worker_id' => 'This worker’s clearance has expired.']);
        }
        if (ManpowerProposal::where('request_id', $manpowerRequest->id)
            ->where('worker_id', $worker->id)
            ->where('status', ManpowerProposal::STATUS_PROPOSED)
            ->exists()) {
            throw ValidationException::withMessages(['worker_id' => 'This worker was already proposed for this request.']);
        }
        if (\App\Models\Hrm\ManpowerAssignment::where('request_id', $manpowerRequest->id)
            ->where('worker_id', $worker->id)
            ->whereNull('released_at')
            ->exists()) {
            throw ValidationException::withMessages(['worker_id' => 'This worker is already assigned to this request.']);
        }

        ManpowerProposal::create([
            'request_id' => $manpowerRequest->id,
            'worker_id' => $worker->id,
            'status' => ManpowerProposal::STATUS_PROPOSED,
            'proposed_by' => $me->id,
            'proposed_by_name' => $me->name,
        ]);

        AgencyActivity::create([
            'agency_id' => $manpowerRequest->agency_id,
            'worker_id' => $worker->id,
            'request_id' => $manpowerRequest->id,
            'action' => 'worker_proposed',
            'details' => "Worker {$worker->code} proposed for request {$manpowerRequest->code} by {$me->name} (agency).",
            'actor_id' => null,
            'actor_name' => $me->name . ' (agency)',
        ]);
        NotifiesHr::push(
            "Worker proposed: {$worker->name} → {$manpowerRequest->code}",
            "{$me->name} proposed {$worker->code} for review.",
            'hrm.manpower-requests.index'
        );

        return back()->with('success', "Worker {$worker->name} proposed. HR will approve or reject it.");
    }
}
