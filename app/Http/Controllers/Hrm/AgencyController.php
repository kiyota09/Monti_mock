<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Mail\AgencyWelcomeMail;
use App\Models\Hrm\Agency;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\AgencyUser;
use App\Models\Hrm\ManpowerRequest;
use App\Models\Hrm\Worker;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Agency management (HRM > Agencies).
 *
 * Page 1 lists agencies (search / status / expiring-soon filters, 15 per
 * page). Page 2 is the shared Add/Edit form (4 sections). Page 3 is the
 * detail workspace (header actions + Overview / Users tabs).
 *
 * Suspend blocks the agency's users from logging in; Terminate deactivates
 * every user but keeps all records.
 */
class AgencyController extends Controller
{
    use HasPagePermissions;

    // ------------------------------------------------------------------
    // Page 1: Agencies list
    // ------------------------------------------------------------------
    public function index(Request $request)
    {
        $q = Agency::withCount(['users', 'workers']);

        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%"));
        }

        $status = $request->get('status');
        if ($status && $status !== 'All') {
            if ($status === Agency::STATUS_PENDING) {
                // Pending is derived: Active-but-not-started contracts.
                $q->where('status', Agency::STATUS_ACTIVE)
                    ->whereDate('contract_start', '>', today());
            } else {
                $q->where('status', $status)
                    // Stored Active rows whose contract already started (or
                    // has no start date) — excludes derived Pending rows.
                    ->when($status === Agency::STATUS_ACTIVE, fn ($w) => $w->where(
                        fn ($x) => $x->whereNull('contract_start')->orWhereDate('contract_start', '<=', today())
                    ));
            }
        }

        if ($request->boolean('expiring')) {
            $limit = today()->addDays(30)->toDateString();
            $q->where(fn ($w) => $w
                ->where(fn ($x) => $x->whereNotNull('contract_end')->whereDate('contract_end', '<=', $limit))
                ->orWhere(fn ($x) => $x->whereNotNull('dole_expiry')->whereDate('dole_expiry', '<=', $limit)));
        }

        $paginated = $q->orderBy('name')->paginate(15)->withQueryString();

        $agencies = collect($paginated->items())->map(fn ($a) => $this->row($a))->values()->all();

        $base = Agency::query();
        $expiringLimit = today()->addDays(30)->toDateString();
        $expiringCount = (clone $base)->where(
            fn ($w) => $w->where(fn ($x) => $x->whereNotNull('contract_end')->whereDate('contract_end', '<=', $expiringLimit))
                ->orWhere(fn ($x) => $x->whereNotNull('dole_expiry')->whereDate('dole_expiry', '<=', $expiringLimit))
        )->count();

        return Inertia::render('Dashboard/HRM_NEW/Agencies/Index', [
            'agencies' => $agencies,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'stats' => [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('status', Agency::STATUS_ACTIVE)->count(),
                'suspended' => (clone $base)->where('status', Agency::STATUS_SUSPENDED)->count(),
                'terminated' => (clone $base)->where('status', Agency::STATUS_TERMINATED)->count(),
                'expiring' => $expiringCount,
            ],
            'filters' => $request->only(['search', 'status', 'expiring']),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    // ------------------------------------------------------------------
    // Page 2: Add / Edit form
    // ------------------------------------------------------------------
    public function create()
    {
        return Inertia::render('Dashboard/HRM_NEW/Agencies/Form', [
            'agency' => null,
            'suggested_code' => self::nextCode(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if (empty($data['code'])) {
            $data['code'] = self::nextCode();
        }
        $data['status'] = Agency::STATUS_ACTIVE;

        $agency = Agency::create($data);
        $this->log($agency, 'created', "Agency {$agency->code} created.", null);

        return redirect()->route('hrm.agencies.show', $agency)
            ->with('success', "Agency {$agency->name} saved.");
    }

    public function edit(Agency $agency)
    {
        return Inertia::render('Dashboard/HRM_NEW/Agencies/Form', [
            'agency' => $this->detail($agency->loadMissing(['users', 'activities'])),
            'suggested_code' => $agency->code,
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function update(Request $request, Agency $agency)
    {
        $data = $this->validated($request, $agency->id);

        if (empty($data['code'])) {
            $data['code'] = $agency->code;
        }

        $agency->update($data);
        $this->log($agency, 'updated', "Agency {$agency->code} updated.", null);

        return redirect()->route('hrm.agencies.show', $agency)
            ->with('success', "Agency {$agency->name} updated.");
    }

    // ------------------------------------------------------------------
    // Page 3: Agency detail
    // ------------------------------------------------------------------
    public function show(Agency $agency)
    {
        $agency->loadMissing(['users', 'activities', 'workers', 'manpowerRequests', 'billingStatements']);

        return Inertia::render('Dashboard/HRM_NEW/Agencies/Detail', [
            'agency' => $this->detail($agency),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function suspend(Request $request, Agency $agency)
    {
        $request->validate(['reason' => 'required|string|max:2000']);

        if ($agency->status !== Agency::STATUS_ACTIVE) {
            return back()->with('error', 'Only active agencies can be suspended.');
        }

        $agency->update(['status' => Agency::STATUS_SUSPENDED]);
        // Suspended agencies keep their user rows, but every login is
        // refused until reactivation (see AgencyAuthController + middleware).
        $this->log($agency, 'suspended', "Agency {$agency->code} suspended. User logins blocked.", $request->reason);

        return back()->with('success', "Agency {$agency->name} suspended. Its users can no longer log in.");
    }

    public function reactivate(Request $request, Agency $agency)
    {
        if ($agency->status !== Agency::STATUS_SUSPENDED) {
            return back()->with('error', 'Only suspended agencies can be reactivated.');
        }

        $agency->update(['status' => Agency::STATUS_ACTIVE]);
        $this->log($agency, 'reactivated', "Agency {$agency->code} reactivated.", null);

        return back()->with('success', "Agency {$agency->name} reactivated.");
    }

    public function terminate(Request $request, Agency $agency)
    {
        $request->validate(['reason' => 'required|string|max:2000']);

        if ($agency->status === Agency::STATUS_TERMINATED) {
            return back()->with('error', 'This contract is already terminated.');
        }

        $agency->update(['status' => Agency::STATUS_TERMINATED, 'terminated_at' => now()]);
        // Records are kept — users are only deactivated (never deleted).
        $agency->users()->update(['is_active' => false, 'remember_token' => null]);
        $this->log($agency, 'terminated', "Contract {$agency->code} terminated. All users deactivated; records kept.", $request->reason);

        // Cross-rules with agency status:
        // - every active assignment of this agency's workers is released
        //   (deployed workers become Inactive along with Available ones),
        // - every open request is Cancelled with "Agency terminated".
        foreach ($agency->workers as $worker) {
            foreach ($worker->activeAssignment as $assignment) {
                $assignment->update(['released_at' => now()]);
            }
            if (in_array($worker->status, [Worker::STATUS_AVAILABLE, Worker::STATUS_DEPLOYED], true)) {
                $worker->update(['status' => Worker::STATUS_INACTIVE]);
                $this->logWorker($agency, $worker, 'worker_deactivated', "Worker {$worker->code} set Inactive — agency terminated.", 'Agency terminated');
            }
        }
        foreach ($agency->manpowerRequests()->where('status', ManpowerRequest::STATUS_OPEN)->get() as $req) {
            $req->update(['status' => ManpowerRequest::STATUS_CANCELLED, 'cancel_reason' => 'Agency terminated']);
            $this->logRequest($agency, $req, 'request_cancelled', "Request {$req->code} cancelled — agency terminated.", 'Agency terminated');
        }

        return back()->with('success', "Contract for {$agency->name} terminated. Users deactivated; records kept.");
    }

    // ------------------------------------------------------------------
    // Tab 2: agency users
    // ------------------------------------------------------------------
    public function storeUser(Request $request, Agency $agency)
    {
        if ($agency->status === Agency::STATUS_TERMINATED) {
            return back()->withErrors(['email' => 'Cannot add users to a terminated agency.']);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:agency_users,email',
        ]);

        $tempPassword = 'Agy-' . Str::upper(Str::random(8)); // 12+ chars

        $user = $agency->users()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $tempPassword, // hashed via cast
            'is_active' => true,
            // First login forces a password change.
            'must_change_password' => true,
        ]);

        $this->log($agency, 'user_added', "Login created for {$user->name} ({$user->email}).", null);

        // The temporary password is emailed to the new login — it is never
        // shown on screen.
        try {
            Mail::to($user->email)->send(new AgencyWelcomeMail(
                $user->name,
                $agency->name,
                $user->email,
                $tempPassword,
                route('agency.login')
            ));

            return back()->with('success', "Login created for {$user->name}. Temporary password emailed to {$user->email}.");
        } catch (\Throwable $e) {
            Log::warning('Agency credentials email failed for user #' . $user->id . ': ' . $e->getMessage());

            return back()->with('error', "Login created for {$user->name}, but the email could not be sent. Use Reset password to email a new temporary password.");
        }
    }

    public function resetUserPassword(Request $request, Agency $agency, AgencyUser $user)
    {
        abort_unless((int) $user->agency_id === (int) $agency->id, 404);

        $tempPassword = 'Agy-' . Str::upper(Str::random(8)); // 12+ chars
        $user->update(['password' => $tempPassword, 'must_change_password' => true]); // hashed via cast

        $this->log($agency, 'password_reset', "Password reset for {$user->name} ({$user->email}).", null);

        try {
            Mail::to($user->email)->send(new AgencyWelcomeMail(
                $user->name,
                $agency->name,
                $user->email,
                $tempPassword,
                route('agency.login'),
                true
            ));

            return back()->with('success', "Password reset for {$user->name}. New temporary password emailed to {$user->email}.");
        } catch (\Throwable $e) {
            Log::warning('Agency password-reset email failed for user #' . $user->id . ': ' . $e->getMessage());

            return back()->with('error', "Password was reset for {$user->name}, but the email could not be sent. Try resetting again.");
        }
    }

    public function toggleUserStatus(Request $request, Agency $agency, AgencyUser $user)
    {
        abort_unless((int) $user->agency_id === (int) $agency->id, 404);

        $user->update([
            'is_active' => ! $user->is_active,
            'remember_token' => $user->is_active ? null : $user->remember_token,
        ]);

        $action = $user->is_active ? 'user_reactivated' : 'user_deactivated';
        $this->log($agency, $action, "Login {$user->email} " . ($user->is_active ? 'reactivated.' : 'deactivated.'), null);

        return back()->with('success', "Login for {$user->name} " . ($user->is_active ? 'reactivated.' : 'deactivated.'));
    }

    // ------------------------------------------------------------------
    // Payloads
    // ------------------------------------------------------------------
    protected function row(Agency $agency): array
    {
        return [
            'id' => $agency->id,
            'code' => $agency->code,
            'name' => $agency->name,
            'contact_name' => $agency->contact_name,
            'users_count' => (int) ($agency->users_count ?? $agency->users()->count()),
            'workers_count' => (int) ($agency->workers_count ?? $agency->workers()->count()),
            'contract_end' => optional($agency->contract_end)->toDateString(),
            'status' => $agency->effective_status,
            'stored_status' => $agency->status,
            'expiring_soon' => (bool) $agency->expiring_soon,
        ];
    }

    protected function detail(Agency $agency): array
    {
        return [
            'id' => $agency->id,
            'code' => $agency->code,
            'name' => $agency->name,
            'tin' => $agency->tin,
            'address' => $agency->address,
            'dole_number' => $agency->dole_number,
            'dole_expiry' => optional($agency->dole_expiry)->toDateString(),
            'contract_start' => optional($agency->contract_start)->toDateString(),
            'contract_end' => optional($agency->contract_end)->toDateString(),
            'service_fee_type' => $agency->service_fee_type,
            'service_fee_value' => $agency->service_fee_value,
            'contact_name' => $agency->contact_name,
            'contact_email' => $agency->contact_email,
            'contact_phone' => $agency->contact_phone,
            'status' => $agency->effective_status,
            'stored_status' => $agency->status,
            'expiring_soon' => (bool) $agency->expiring_soon,
            'users' => $agency->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => (bool) $u->is_active,
            ])->values()->all(),
            'activities' => $agency->activities
                ->whereNull('worker_id')->whereNull('request_id')->take(20)->map(fn ($a) => [
                'id' => $a->id,
                'date' => optional($a->created_at)->format('M d, Y h:i A'),
                'action' => $a->action,
                'details' => $a->details,
                'actor' => $a->actor_name ?? ('#' . $a->actor_id),
                'reason' => $a->reason,
            ])->values()->all(),
            'workers' => $agency->workers->map(fn ($w) => [
                'id' => $w->id,
                'code' => $w->code,
                'name' => $w->name,
                'position_skill' => $w->position_skill,
                'status' => $w->status,
            ])->values()->all(),
            'requests' => $agency->manpowerRequests->map(fn ($r) => [
                'id' => $r->id,
                'code' => $r->code,
                'department' => $r->department,
                'position_skill' => $r->position_skill,
                'needed' => (int) $r->headcount,
                'filled' => (int) $r->filled_count,
                'status' => $r->effective_status,
            ])->values()->all(),
            'attendance' => \App\Models\Hrm\Attendance::with(['worker:id,code,name', 'request:id,code'])
                ->whereHas('worker', fn ($q) => $q->where('agency_id', $agency->id))
                ->orderByDesc('date')->take(50)->get()->map(fn ($a) => [
                    'id' => $a->id,
                    'date' => $a->date->toDateString(),
                    'worker_name' => $a->worker?->name,
                    'worker_code' => $a->worker?->code,
                    'request_code' => $a->request?->code,
                    'status' => $a->status,
                    'approval' => $a->approval,
                ])->values()->all(),
            'statements' => $agency->billingStatements->sortByDesc('id')->take(20)->map(fn ($s) => [
                'id' => $s->id,
                'code' => $s->code,
                'period' => \App\Support\BillingCalc::periodLabel($s->period_start->toDateString(), $s->period_end->toDateString()),
                'total' => $s->total,
                'status' => $s->status,
            ])->values()->all(),
        ];
    }

    protected function rules(?int $ignoreId = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:30', Rule::unique('agencies', 'code')->ignore($ignoreId)],
            'tin' => ['nullable', 'string', 'max:60', Rule::unique('agencies', 'tin')->ignore($ignoreId)],
            'address' => 'nullable|string|max:2000',
            'dole_number' => ['nullable', 'string', 'max:100', Rule::unique('agencies', 'dole_number')->ignore($ignoreId)],
            'dole_expiry' => 'nullable|date',
            'contract_start' => 'nullable|date',
            'contract_end' => 'nullable|date',
            'service_fee_type' => 'required|in:percentage,fixed',
            'service_fee_value' => 'required|numeric|min:0',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:60',
        ];
    }

    /**
     * Validate the agency form: blanks become null (so optional uniques
     * never collide on empty strings) and contract end is refused when it
     * predates contract start — error lands under the end-date field.
     */
    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        foreach (['code', 'tin', 'dole_number'] as $key) {
            if ($request->get($key) === '') {
                $request->merge([$key => null]);
            }
        }

        $data = $request->validate($this->rules($ignoreId));

        if (! empty($data['contract_start']) && ! empty($data['contract_end'])
            && $data['contract_end'] < $data['contract_start']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'contract_end' => 'Contract end cannot be before contract start.',
            ]);
        }

        return $data;
    }

    protected static function nextCode(): string
    {
        $next = (int) (Agency::max('id') ?? 0) + 1;
        do {
            $code = 'AGY-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (Agency::where('code', $code)->exists());

        return $code;
    }

    protected function log(Agency $agency, string $action, ?string $details, ?string $reason): void
    {
        AgencyActivity::create([
            'agency_id' => $agency->id,
            'action' => $action,
            'details' => $details,
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
            'reason' => $reason,
        ]);
    }

    protected function logWorker(Agency $agency, Worker $worker, string $action, ?string $details, ?string $reason): void
    {
        AgencyActivity::create([
            'agency_id' => $agency->id,
            'worker_id' => $worker->id,
            'action' => $action,
            'details' => $details,
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
            'reason' => $reason,
        ]);
    }

    protected function logRequest(Agency $agency, ManpowerRequest $request, string $action, ?string $details, ?string $reason): void
    {
        AgencyActivity::create([
            'agency_id' => $agency->id,
            'request_id' => $request->id,
            'action' => $action,
            'details' => $details,
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
            'reason' => $reason,
        ]);
    }
}
