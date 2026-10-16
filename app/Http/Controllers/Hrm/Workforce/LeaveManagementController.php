<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\HrmLeaveBalance;
use App\Models\Hrm\HrmLeaveType;
use App\Models\Hrm\LeaveRequest;
use App\Traits\HasPagePermissions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaveManagementController extends Controller
{
    use HasPagePermissions;

    /**
     * HRM manager view: org leave balances + request queue.
     * Reuses the canonical `leave_requests` table so the employee
     * portal (Users\LeaveController) and Workforce module stay in sync.
     */
    public function index(Request $request)
    {
        $types = HrmLeaveType::where('is_active', true)->orderBy('id')->get();

        // Org-wide usage per type (approved days, inclusive).
        $usage = [];
        foreach (LeaveRequest::where('status', 'approved')->get(['leave_type', 'start_date', 'end_date']) as $lr) {
            $usage[strtolower((string) $lr->leave_type)] = ($usage[strtolower((string) $lr->leave_type)] ?? 0) + $this->days($lr->start_date, $lr->end_date);
        }

        $balances = $types->map(function ($t) use ($usage) {
            $used = $usage[strtolower($t->code)] ?? $usage[strtolower($t->name)] ?? 0;
            $remaining = max(0, (int) $t->default_days - (int) $used);
            $base = max(1, $remaining + $used);
            return [
                'code' => $t->code,
                'name' => $t->name,
                'remaining' => (string) $remaining,
                'used' => (string) $used,
                'percent' => (int) round($remaining / $base * 100),
            ];
        })->values()->all();

        $q = LeaveRequest::with('user')->latest();
        if ($s = $request->get('search')) {
            $q->whereHas('user', fn ($w) => $w->where('name', 'like', "%{$s}%"))
                ->orWhere('leave_type', 'like', "%{$s}%");
        }
        if ($st = $request->get('status')) {
            $q->where('status', strtolower($st));
        }

        $paginated = $q->paginate(15)->withQueryString();
        $leaveRequests = collect($paginated->items())->filter(fn ($r) => $r !== null)->map(fn ($lr) => [
            'id' => $lr->id,
            'user_id' => $lr->user_id,
            'employee' => $lr->user?->name ?? ('User #' . $lr->user_id),
            'type' => $this->label($lr->leave_type),
            'leave_type' => $lr->leave_type,
            'dates' => $this->range($lr->start_date, $lr->end_date),
            'start_date' => optional($lr->start_date)->format('Y-m-d'),
            'end_date' => optional($lr->end_date)->format('Y-m-d'),
            'days' => $this->days($lr->start_date, $lr->end_date),
            'status' => ucfirst(strtolower((string) $lr->status)),
            'reason' => $lr->reason,
        ])->values()->all();

        return Inertia::render('Dashboard/HRM_NEW/LeaveManagement', [
            'balances' => $balances,
            'leaveRequests' => $leaveRequests,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'stats' => [
                'pending' => LeaveRequest::where('status', 'pending')->count(),
                'approved' => LeaveRequest::where('status', 'approved')->count(),
                'rejected' => LeaveRequest::where('status', 'rejected')->count(),
            ],
            'filters' => $request->only(['search', 'status']),
            'leaveTypes' => $types->map(fn ($t) => ['code' => $t->code, 'name' => $t->name])->values()->all(),
            'employees' => User::where('is_active', true)->orderBy('name')->select('id', 'name')->take(200)->get(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'leave_type' => 'required|string|max:60',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
        ]);
        LeaveRequest::create($data + ['status' => 'pending']);

        return back()->with('success', 'Leave request filed.');
    }

    public function approve(LeaveRequest $leave)
    {
        $leave->update(['status' => 'approved']);
        $this->bumpBalance($leave, +1);

        return back()->with('success', 'Leave approved.');
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        // Was it previously approved? Roll the balance back.
        $wasApproved = strtolower((string) $leave->status) === 'approved';
        $leave->update(['status' => 'rejected']);
        if ($wasApproved) {
            $this->bumpBalance($leave, -1);
        }

        return back()->with('success', 'Leave rejected.');
    }

    private function days($start, $end): int
    {
        try {
            return max(1, Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1);
        } catch (\Throwable) {
            return 1;
        }
    }

    private function range($start, $end): string
    {
        try {
            return Carbon::parse($start)->format('M d') . ' - ' . Carbon::parse($end)->format('M d');
        } catch (\Throwable) {
            return '';
        }
    }

    private function label($code): string
    {
        $type = HrmLeaveType::where('code', strtolower((string) $code))->first();
        if ($type) {
            return $type->name;
        }
        return ucwords(str_replace(['_', '-'], ' ', (string) $code));
    }

    private function bumpBalance(LeaveRequest $leave, int $dir): void
    {
        $type = HrmLeaveType::where('code', strtolower((string) $leave->leave_type))->first();
        if (! $type) {
            return;
        }
        $days = $this->days($leave->start_date, $leave->end_date);
        $bal = HrmLeaveBalance::firstOrCreate(
            ['user_id' => $leave->user_id, 'leave_type_id' => $type->id, 'year' => (int) now()->year],
            ['entitled' => $type->default_days, 'used' => 0]
        );
        $bal->update(['used' => max(0, (int) $bal->used + $dir * $days)]);
    }
}
