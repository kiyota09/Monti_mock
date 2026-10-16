<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\Attendance;
use App\Support\NotifiesHr;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Agency portal: read-only attendance for the agency's workers, plus
 * Confirm / Dispute (reason required) on Approved rows inside the 3-day
 * window. Disputes go back to HR.
 */
class AttendancePortalController extends Controller
{
    private function agencyId(Request $request): int
    {
        return (int) $request->user('agency')->agency_id;
    }

    public function index(Request $request)
    {
        $agencyId = $this->agencyId($request);
        $q = Attendance::with(['worker:id,code,name', 'request:id,code,department'])
            ->whereHas('worker', fn ($w) => $w->where('agency_id', $agencyId));

        if ($requestId = $request->get('request_id')) {
            $q->where('request_id', $requestId);
        }
        if ($from = $request->get('from')) {
            $q->whereDate('date', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $q->whereDate('date', '<=', $to);
        }

        $paginated = $q->orderByDesc('date')->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Agency/Attendance/Index', [
            'records' => collect($paginated->items())->map(fn ($a) => [
                'id' => $a->id,
                'date' => $a->date->toDateString(),
                'worker_name' => $a->worker?->name,
                'worker_code' => $a->worker?->code,
                'request_code' => $a->request?->code,
                'department' => $a->request?->department,
                'status' => $a->status,
                'hours' => $a->hours,
                'overtime_hours' => $a->overtime_hours,
                'approval' => $a->approval,
                'confirmation' => $a->display_confirmation,
                'confirmable' => (bool) $a->confirmable,
                'dispute_reason' => $a->dispute_reason,
            ])->values()->all(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'filters' => $request->only(['request_id', 'from', 'to']),
        ]);
    }

    public function confirm(Request $request, Attendance $attendance)
    {
        $this->assertOwn($request, $attendance);

        if (! $attendance->confirmable) {
            return back()->with('error', 'This record can no longer be confirmed.');
        }

        $attendance->update(['agency_confirmation' => 'Confirmed']);
        $this->log($request, $attendance, 'attendance_confirmed', "Attendance for {$attendance->worker->name} on {$attendance->date->toDateString()} confirmed by {$request->user('agency')->name} (agency).", null);

        return back()->with('success', 'Attendance confirmed.');
    }

    public function dispute(Request $request, Attendance $attendance)
    {
        $request->validate(['reason' => 'required|string|max:2000']);
        $this->assertOwn($request, $attendance);

        if (! $attendance->confirmable) {
            return back()->with('error', 'This record can no longer be disputed (3-day window).');
        }

        $attendance->update([
            'agency_confirmation' => 'Disputed',
            'dispute_reason' => $request->reason,
            'disputed_at' => now(),
            'disputed_by_name' => $request->user('agency')->name,
        ]);
        $this->log($request, $attendance, 'attendance_disputed', "Attendance for {$attendance->worker->name} on {$attendance->date->toDateString()} disputed by {$request->user('agency')->name} (agency).", $request->reason);
        NotifiesHr::push(
            "Attendance disputed: {$attendance->worker->name}",
            "{$request->user('agency')->name} disputed {$attendance->date->toDateString()} — {$request->reason}",
            'hrm.attendance.approval'
        );

        return back()->with('success', 'Dispute sent back to HR.');
    }

    protected function assertOwn(Request $request, Attendance $attendance): void
    {
        $attendance->loadMissing('worker:id,agency_id');
        if (! $attendance->worker || (int) $attendance->worker->agency_id !== $this->agencyId($request)) {
            abort(404);
        }
        if ($attendance->approval !== Attendance::APPROVAL_APPROVED) {
            throw ValidationException::withMessages(['record' => 'Only Approved records can be confirmed or disputed.']);
        }
    }

    protected function log(Request $request, Attendance $attendance, string $action, ?string $details, ?string $reason): void
    {
        AgencyActivity::create([
            'agency_id' => $this->agencyId($request),
            'worker_id' => $attendance->worker_id,
            'request_id' => $attendance->request_id,
            'action' => $action,
            'details' => $details,
            'actor_id' => null,
            'actor_name' => $request->user('agency')->name . ' (agency)',
            'reason' => $reason,
        ]);
    }
}
