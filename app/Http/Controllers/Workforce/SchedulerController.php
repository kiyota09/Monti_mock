<?php

namespace App\Http\Controllers\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Hrm\EmployeeShift;
use App\Models\Hrm\Holiday;
use App\Models\Core\User;
use App\Models\Ceo\CeoPlanner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SchedulerController extends Controller
{
    /**
     * Display the scheduler.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user->canAccessWorkforce(null, null, 'schedule')) {
            abort(403);
        }

        // All active roles, used by the CEO to toggle between modules
        $allModules = User::where('is_active', true)
            ->pluck('role')
            ->filter()
            ->unique()
            ->values();

        // ── Approved holidays (shared by every view) ────────────────
        $holidays = Holiday::where('status', 'approved')->get()->map(fn ($h) => [
            'id'           => $h->id,
            'date'         => substr((string)$h->holiday_date, 0, 10),
            'name'         => $h->holiday_name,
            'type'         => $h->holiday_type,
            'premium_rate' => $h->premium_rate,
        ]);

        // ── CEO: read-only schedule view for all modules ────────────
        if ($user->role === 'CEO' && $request->get('ceo_view') === 'schedules') {
            $selectedModule = $request->get('module', 'ALL');

            $usersQuery = User::where('is_active', true);
            if ($selectedModule !== 'ALL') {
                $usersQuery->where('role', $selectedModule);
            }
            $employees = $usersQuery->get(['id', 'name', 'role', 'manufacturing_role']);

            $shifts = EmployeeShift::with('user')
                ->whereIn('user_id', $employees->pluck('id'))
                ->orderBy('effective_date')
                ->get()
                ->map(fn ($s) => [
                    'id'            => $s->id,
                    'user_id'       => $s->user_id,
                    'user_name'     => $s->user->name,
                    'shift_type'    => $s->shift_type,
                    'effective_date'=> substr((string)$s->effective_date, 0, 10),
                    'schedule_range'=> $s->schedule_range,
                ]);

            return Inertia::render('Dashboard/Workforce/Scheduler', [
                'employees'      => $employees->values(),
                'shifts'         => $shifts,
                'holidays'       => $holidays,
                'isCeoMode'      => true,
                'ceoView'        => 'schedules',
                'selectedModule' => $selectedModule,
                'allModules'     => $allModules,
            ]);
        }

        // ── CEO: personal planner view ──────────────────────────────
        if ($user->role === 'CEO') {
            $events = CeoPlanner::where('user_id', $user->id)
                ->orderBy('event_date')
                ->orderBy('start_time')
                ->get()
                ->map(fn ($e) => [
                    'id'         => $e->id,
                    'title'      => $e->title,
                    'event_date' => substr((string)$e->event_date, 0, 10),
                    'start_time' => $e->start_time ? Carbon::parse($e->start_time)->format('H:i') : null,
                    'end_time'   => $e->end_time   ? Carbon::parse($e->end_time)->format('H:i')   : null,
                    'location'   => $e->location,
                    'attendee'   => $e->attendee,
                    'notes'      => $e->notes,
                ]);

            return Inertia::render('Dashboard/Workforce/Scheduler', [
                'employees'  => [],
                'shifts'     => $events,
                'holidays'   => $holidays,
                'isCeoMode'  => true,
                'ceoView'    => 'planner',
                'allModules' => $allModules,
            ]);
        }

        // ── Normal: shift scheduler for permissioned modules ────────
        $selectedModule = $request->get('module', 'ALL');
        $usersQuery     = User::where('is_active', true);
        $perms          = $user->workforcePermissions;

        if ($perms->isNotEmpty()) {
            $modules     = $perms->pluck('module')->filter()->unique();
            $departments = $perms->pluck('department')->filter()->unique();
            $usersQuery->where(function ($q) use ($modules, $departments) {
                if ($modules->isNotEmpty())     $q->whereIn('role', $modules);
                if ($departments->isNotEmpty()) $q->whereIn('manufacturing_role', $departments);
            });
        }

        $employees = $usersQuery->get(['id', 'name', 'role', 'manufacturing_role']);

        if ($selectedModule !== 'ALL') {
            $employees = $employees->filter(fn ($e) => $e->role === $selectedModule);
        }

        $shifts = EmployeeShift::with('user')
            ->whereIn('user_id', $employees->pluck('id'))
            ->orderBy('effective_date')
            ->get()
            ->map(fn ($s) => [
                'id'             => $s->id,
                'user_id'        => $s->user_id,
                'user_name'      => $s->user->name,
                'shift_type'     => $s->shift_type,
                'effective_date' => substr((string)$s->effective_date, 0, 10),
                'schedule_range' => $s->schedule_range,
            ]);

        return Inertia::render('Dashboard/Workforce/Scheduler', [
            'employees'      => $employees->values(),
            'shifts'         => $shifts,
            'holidays'       => $holidays,
            'isCeoMode'      => false,
            'ceoView'        => null,
            'selectedModule' => $selectedModule,
            'allModules'     => [],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Shift CRUD
    // ──────────────────────────────────────────────────────────────────

    public function storeShift(Request $request)
    {
        $user = Auth::user();
        if (! $user->canAccessWorkforce(null, null, 'schedule')) abort(403);

        $request->validate([
            'user_id'        => 'required|exists:users,id',
            'shift_type'     => 'required|in:Morning,Afternoon,Graveyard',
            'effective_date' => 'required|date',
            'schedule_range' => 'nullable|string',
        ]);

        $holiday = Holiday::where('holiday_date', $request->effective_date)
            ->whereIn('holiday_type', ['regular', 'special_non_working'])
            ->first();

        if ($holiday) {
            return back()->withErrors(['effective_date' => "Cannot assign shift on non-working holiday: {$holiday->holiday_name}"]);
        }

        // FIX: Use withTrashed() to find soft-deleted shifts to prevent 1062 Duplicate Entry errors.
        $shift = EmployeeShift::withTrashed()->firstOrNew([
            'user_id' => $request->user_id, 
            'effective_date' => substr($request->effective_date, 0, 10)
        ]);

        // Restore and update values
        $shift->shift_type = $request->shift_type;
        $shift->schedule_range = $request->schedule_range ?? $this->getShiftRange($request->shift_type);
        $shift->status = 'approved';
        $shift->deleted_at = null; // Restore if it was previously soft-deleted
        $shift->save();

        return back()->with('message', 'Shift scheduled.');
    }

    public function deleteShift($id)
    {
        $shift = EmployeeShift::findOrFail($id);
        if (! Auth::user()->canAccessWorkforce(null, null, 'schedule')) abort(403);
        $shift->delete();
        return back()->with('message', 'Shift removed.');
    }

    // ──────────────────────────────────────────────────────────────────
    // Holiday CRUD
    // ──────────────────────────────────────────────────────────────────

    public function storeHoliday(Request $request)
    {
        $user = Auth::user();
        if (! $user->canAccessWorkforce(null, null, 'manage')) abort(403);

        $request->validate([
            'holiday_date' => 'required|date|unique:holidays,holiday_date',
            'holiday_name' => 'required|string',
            'holiday_type' => 'required|in:regular,special_non_working,special_working,non_working',
            'premium_rate' => 'nullable|numeric|min:0',
        ]);

        if (in_array($request->holiday_type, ['regular', 'special_non_working', 'non_working'])) {
            EmployeeShift::where('effective_date', substr($request->holiday_date, 0, 10))->delete();
        }

        Holiday::create([
            'holiday_date' => substr($request->holiday_date, 0, 10),
            'holiday_name' => $request->holiday_name,
            'holiday_type' => $request->holiday_type,
            'premium_rate' => $request->premium_rate ?? 1.00,
            'status'       => 'approved',
        ]);

        return back()->with('message', 'Holiday added.');
    }

    public function updateHoliday(Request $request, $id)
    {
        $user = Auth::user();
        if (! $user->canAccessWorkforce(null, null, 'manage')) abort(403);

        $request->validate([
            'holiday_date' => 'required|date|unique:holidays,holiday_date,' . $id,
            'holiday_name' => 'required|string',
            'holiday_type' => 'required|in:regular,special_non_working,special_working,non_working',
            'premium_rate' => 'nullable|numeric|min:0',
        ]);

        $holiday = Holiday::findOrFail($id);
        $oldDate = substr((string)$holiday->holiday_date, 0, 10);
        $newDate = substr((string)$request->holiday_date, 0, 10);

        if ($oldDate !== $newDate && in_array($request->holiday_type, ['regular', 'special_non_working', 'non_working'])) {
            EmployeeShift::where('effective_date', $newDate)->delete();
        }

        $holiday->update([
            'holiday_date' => $newDate,
            'holiday_name' => $request->holiday_name,
            'holiday_type' => $request->holiday_type,
            'premium_rate' => $request->premium_rate
        ]);

        return back()->with('message', 'Holiday updated.');
    }

    public function deleteHoliday($id)
    {
        $holiday = Holiday::findOrFail($id);
        if (! Auth::user()->canAccessWorkforce(null, null, 'manage')) abort(403);
        $holiday->delete();
        return back()->with('message', 'Holiday removed.');
    }

    // ──────────────────────────────────────────────────────────────────
    // Bulk Shift Assignment
    // ──────────────────────────────────────────────────────────────────

    public function storeBulkShift(Request $request)
    {
        $user = Auth::user();
        if (! $user->canAccessWorkforce(null, null, 'schedule')) abort(403);

        $request->validate([
            'user_id'        => 'required|exists:users,id',
            'action'         => 'required|in:assign,clear',
            'shift_type'     => 'required_if:action,assign|in:Morning,Afternoon,Graveyard',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'schedule_range' => 'nullable|string',
        ]);

        $start = Carbon::parse($request->start_date);
        $end   = Carbon::parse($request->end_date);
        $dates = [];
        
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        if ($request->action === 'clear') {
            EmployeeShift::where('user_id', $request->user_id)
                ->whereBetween('effective_date', [$start->toDateString(), $end->toDateString()])
                ->delete();
            return back()->with('message', 'Shifts cleared for the selected period.');
        }

        $nonWorkingHolidays = Holiday::whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('holiday_type', ['regular', 'special_non_working'])
            ->pluck('holiday_date')
            ->map(fn($d) => substr((string)$d, 0, 10))
            ->toArray();

        foreach ($dates as $date) {
            if (in_array($date, $nonWorkingHolidays)) continue;
            
            // FIX: Use withTrashed() and restore to prevent 1062 Duplicate errors on bulk assigns
            $shift = EmployeeShift::withTrashed()->firstOrNew([
                'user_id' => $request->user_id, 
                'effective_date' => $date
            ]);

            $shift->shift_type = $request->shift_type;
            $shift->schedule_range = $request->schedule_range ?? $this->getShiftRange($request->shift_type);
            $shift->status = 'approved';
            $shift->deleted_at = null; // Restore soft-deleted shift
            $shift->save();
        }

        return back()->with('message', 'Bulk shifts assigned (non-working holidays skipped).');
    }

    private function getShiftRange(string $type): string
    {
        return ['Morning' => '08:00 – 17:00', 'Afternoon' => '16:00 – 01:00', 'Graveyard' => '00:00 – 09:00'][$type] ?? '';
    }

    // ──────────────────────────────────────────────────────────────────
    // CEO Planner CRUD
    // ──────────────────────────────────────────────────────────────────

    public function storePlannerEvent(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'CEO') abort(403);

        $request->validate([
            'event_date' => 'required|date',
            'title'      => 'required|string|max:255',
            'start_time' => 'nullable|date_format:H:i',
            'end_time'   => 'nullable|date_format:H:i|after:start_time',
            'location'   => 'nullable|string|max:255',
            'attendee'   => 'nullable|string|max:255',
            'notes'      => 'nullable|string',
        ]);

        CeoPlanner::create([
            'user_id'    => $user->id,
            'event_date' => substr($request->event_date, 0, 10), 
            'title'      => $request->title,
            'start_time' => $request->start_time,
            'end_time'   => $request->end_time,
            'location'   => $request->location,
            'attendee'   => $request->attendee,
            'notes'      => $request->notes,
        ]);

        return back()->with('message', 'Event added to planner.');
    }

    public function updatePlannerEvent(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role !== 'CEO') abort(403);

        $event = CeoPlanner::where('user_id', $user->id)->findOrFail($id);

        $request->validate([
            'event_date' => 'required|date',
            'title'      => 'required|string|max:255',
            'start_time' => 'nullable|date_format:H:i',
            'end_time'   => 'nullable|date_format:H:i|after:start_time',
            'location'   => 'nullable|string|max:255',
            'attendee'   => 'nullable|string|max:255',
            'notes'      => 'nullable|string',
        ]);

        $event->update([
            'event_date' => substr($request->event_date, 0, 10),
            'title'      => $request->title,
            'start_time' => $request->start_time,
            'end_time'   => $request->end_time,
            'location'   => $request->location,
            'attendee'   => $request->attendee,
            'notes'      => $request->notes,
        ]);

        return back()->with('message', 'Event updated.');
    }

    public function deletePlannerEvent($id)
    {
        $user  = Auth::user();
        if ($user->role !== 'CEO') abort(403);

        $event = CeoPlanner::where('user_id', $user->id)->findOrFail($id);
        $event->delete();

        return back()->with('message', 'Event removed.');
    }
}