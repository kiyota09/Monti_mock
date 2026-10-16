<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Agency;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\Worker;
use App\Models\Hrm\WorkerDocument;
use App\Models\Hrm\WorkerRateRequest;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Agency workers (HRM > Workers, Pages 5-7).
 *
 * Lifecycle: Available ⇄ Deployed (automatic on assign/release), Inactive
 * (manual, reversible), Blacklisted (reason required; HR/IT lift it via the
 * lift action — all routes here already require the workers HR grant).
 */
class WorkerController extends Controller
{
    use HasPagePermissions;

    // ------------------------------------------------------------------
    // Page 5: Workers list
    // ------------------------------------------------------------------
    public function index(Request $request)
    {
        $q = Worker::with(['agency:id,code,name', 'activeAssignments.request:id,code,department']);

        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%"));
        }
        if ($agencyId = $request->get('agency_id')) {
            $q->where('agency_id', $agencyId);
        }
        if (($status = $request->get('status')) && $status !== 'All') {
            $q->where('status', $status);
        }
        if ($request->boolean('expiring')) {
            $limit = today()->addDays(30)->toDateString();
            $q->whereNotNull('clearance_expiry')->whereDate('clearance_expiry', '<=', $limit);
        }

        $paginated = $q->orderBy('name')->paginate(15)->withQueryString();
        $workers = collect($paginated->items())->map(fn ($w) => $this->row($w))->values()->all();

        $base = Worker::query();

        return Inertia::render('Dashboard/HRM_NEW/Workers/Index', [
            'workers' => $workers,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'stats' => [
                'total' => (clone $base)->count(),
                'available' => (clone $base)->where('status', Worker::STATUS_AVAILABLE)->count(),
                'deployed' => (clone $base)->where('status', Worker::STATUS_DEPLOYED)->count(),
                'inactive' => (clone $base)->where('status', Worker::STATUS_INACTIVE)->count(),
                'blacklisted' => (clone $base)->where('status', Worker::STATUS_BLACKLISTED)->count(),
                'pending' => (clone $base)->where('status', Worker::STATUS_PENDING)->count(),
            ],
            // HR queues: agency-added workers awaiting approval + pending
            // rate-change requests.
            'pending_workers' => Worker::with('agency:id,code,name')
                ->where('status', Worker::STATUS_PENDING)
                ->orderByDesc('id')->take(100)->get()
                ->map(fn ($w) => [
                    'id' => $w->id, 'code' => $w->code, 'name' => $w->name,
                    'agency' => $w->agency?->name, 'position_skill' => $w->position_skill,
                    'daily_rate' => $w->daily_rate, 'created_at' => optional($w->created_at)->format('M d, Y'),
                ])->values()->all(),
            'rate_requests' => WorkerRateRequest::with(['worker:id,code,name,agency_id', 'worker.agency:id,name'])
                ->where('status', WorkerRateRequest::STATUS_PENDING)
                ->orderByDesc('id')->take(100)->get()
                ->map(fn ($r) => [
                    'id' => $r->id, 'worker_id' => $r->worker_id,
                    'worker_code' => $r->worker?->code, 'worker_name' => $r->worker?->name,
                    'agency' => $r->worker?->agency?->name,
                    'old_rate' => $r->old_rate, 'new_rate' => $r->new_rate,
                    'requested_by' => $r->requested_by_name,
                    'created_at' => optional($r->created_at)->format('M d, Y'),
                ])->values()->all(),
            'agencies' => Agency::orderBy('name')->get(['id', 'code', 'name', 'status']),
            'filters' => $request->only(['search', 'agency_id', 'status', 'expiring']),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    // ------------------------------------------------------------------
    // Page 6: Add / Edit worker
    // ------------------------------------------------------------------
    public function create()
    {
        return Inertia::render('Dashboard/HRM_NEW/Workers/Form', [
            'worker' => null,
            'suggested_code' => self::nextCode(),
            'agencies' => Agency::where('status', Agency::STATUS_ACTIVE)->orderBy('name')->get(['id', 'code', 'name']),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $agency = Agency::findOrFail($data['agency_id']);
        if ($agency->status !== Agency::STATUS_ACTIVE) {
            return back()->withErrors(['agency_id' => 'A worker cannot be added to a Suspended or Terminated agency.']);
        }

        if (empty($data['code'])) {
            $data['code'] = self::nextCode();
        }
        if ($request->hasFile('resume')) {
            $file = $request->file('resume');
            $data['resume_path'] = $file->store('workers/resumes', 'public');
            $data['resume_original_name'] = $file->getClientOriginalName();
        }
        $data['status'] = Worker::STATUS_AVAILABLE;

        $worker = Worker::create($data);
        if (! empty($data['resume_path'])) {
            $worker->documents()->create([
                'doc_type' => 'resume',
                'label' => 'Resume / CV',
                'file_path' => $data['resume_path'],
                'original_name' => $data['resume_original_name'] ?? null,
                'mime' => $request->hasFile('resume') ? $request->file('resume')->getClientMimeType() : null,
                'size' => $request->hasFile('resume') ? $request->file('resume')->getSize() : null,
                'status' => WorkerDocument::STATUS_VERIFIED,
                'verified_by_name' => auth()->user()?->name,
                'verified_at' => now(),
            ]);
        }
        $this->log($worker, 'worker_created', "Worker {$worker->code} ({$worker->name}) added to {$agency->name}.", null);

        return redirect()->route('hrm.workers.show', $worker)
            ->with('success', "Worker {$worker->name} added.");
    }

    public function edit(Worker $worker)
    {
        $worker->loadMissing('agency:id,code,name,status');

        return Inertia::render('Dashboard/HRM_NEW/Workers/Form', [
            'worker' => $this->detail($worker),
            'suggested_code' => $worker->code,
            'agencies' => Agency::where('status', Agency::STATUS_ACTIVE)->orderBy('name')->get(['id', 'code', 'name']),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function update(Request $request, Worker $worker)
    {
        $data = $this->validated($request, $worker->id);

        if (empty($data['code'])) {
            $data['code'] = $worker->code;
        }
        if ($request->hasFile('resume')) {
            if ($worker->resume_path && Storage::disk('public')->exists($worker->resume_path)) {
                Storage::disk('public')->delete($worker->resume_path);
            }
            $file = $request->file('resume');
            $data['resume_path'] = $file->store('workers/resumes', 'public');
            $data['resume_original_name'] = $file->getClientOriginalName();
        }

        // Moving agencies is only possible without an active assignment,
        // and only into an Active agency.
        if ((int) ($data['agency_id'] ?? $worker->agency_id) !== (int) $worker->agency_id) {
            if ($worker->activeAssignment()->exists()) {
                return back()->withErrors(['agency_id' => 'Release this worker from the active assignment before moving agencies.']);
            }
            $target = Agency::findOrFail($data['agency_id']);
            if ($target->status !== Agency::STATUS_ACTIVE) {
                return back()->withErrors(['agency_id' => 'A worker cannot be moved to a Suspended or Terminated agency.']);
            }
        }

        $worker->update($data);
        $this->log($worker->fresh(), 'worker_updated', "Worker {$worker->code} updated.", null);

        return redirect()->route('hrm.workers.show', $worker)
            ->with('success', "Worker {$worker->name} updated.");
    }

    // ------------------------------------------------------------------
    // Page 7: Worker detail
    // ------------------------------------------------------------------
    public function show(Worker $worker)
    {
        $worker->loadMissing(['agency:id,code,name,status', 'assignments.request:id,code,department']);

        return Inertia::render('Dashboard/HRM_NEW/Workers/Detail', [
            'worker' => $this->detail($worker),
            'departments' => \App\Models\Hrm\HrmDepartment::whereNull('archived_at')->orderBy('name')->get(['id', 'code', 'name']),
            'positions' => \App\Models\Hrm\HrmPosition::whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'department_id']),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function deactivate(Request $request, Worker $worker)
    {
        if ($worker->status !== Worker::STATUS_AVAILABLE) {
            return back()->with('error', 'Only Available workers can be rejected.');
        }
        if ($worker->activeAssignment()->exists()) {
            return back()->with('error', 'Release this worker from the active assignment first.');
        }

        $worker->update(['status' => Worker::STATUS_INACTIVE]);
        $this->log($worker, 'worker_deactivated', "Worker {$worker->code} rejected (Inactive).", $request->get('reason'));

        return back()->with('success', "Worker {$worker->name} rejected.");
    }

    /**
     * Re-open a rejected / inactive worker: Inactive → Pending (NOT Available).
     * HR must Approve afterwards — Approve / Request changes / Reject /
     * Blacklist all operate on the Pending re-opened worker.
     */
    public function reactivate(Worker $worker)
    {
        if ($worker->status !== Worker::STATUS_INACTIVE) {
            return back()->with('error', 'Only Inactive workers can be re-opened.');
        }

        $worker->update(['status' => Worker::STATUS_PENDING]);
        $this->log($worker, 'worker_reactivated', "Worker {$worker->code} re-opened (Pending HR approval).", null);

        return back()->with('success', "Worker {$worker->name} re-opened — pending HR approval.");
    }

    public function blacklist(Request $request, Worker $worker)
    {
        $request->validate(['reason' => 'required|string|max:2000']);

        if (! in_array($worker->status, [Worker::STATUS_AVAILABLE, Worker::STATUS_INACTIVE, Worker::STATUS_PENDING, Worker::STATUS_CORRECTION], true)) {
            return back()->with('error', 'Release this worker from the active assignment before blacklisting.');
        }
        if ($worker->activeAssignment()->exists()) {
            return back()->with('error', 'Release this worker from the active assignment before blacklisting.');
        }

        $worker->update(['status' => Worker::STATUS_BLACKLISTED, 'blacklist_reason' => $request->reason]);
        $this->log($worker, 'worker_blacklisted', "Worker {$worker->code} blacklisted.", $request->reason);

        return back()->with('success', "Worker {$worker->name} blacklisted.");
    }

    public function liftBlacklist(Worker $worker)
    {
        if ($worker->status !== Worker::STATUS_BLACKLISTED) {
            return back()->with('error', 'This worker is not blacklisted.');
        }

        // Lifting is an HR/IT-only action: every route here already requires
        // the workers HR grant, so reaching this action is the authorization.
        $worker->update(['status' => Worker::STATUS_AVAILABLE, 'blacklist_reason' => null]);
        $this->log($worker, 'worker_blacklist_lifted', "Blacklist lifted for {$worker->code} (Available).", null);

        return back()->with('success', "Blacklist lifted for {$worker->name}.");
    }

    /** HR approves an agency-added worker: Pending → Available. */
    public function approvePending(Worker $worker)
    {
        if ($worker->status !== Worker::STATUS_PENDING) {
            return back()->with('error', 'Only workers pending HR approval can be approved.');
        }
        if ($worker->agency->status !== Agency::STATUS_ACTIVE) {
            return back()->with('error', 'The agency is no longer Active.');
        }

        // Minimum submission + applicable verification must hold before Available.
        $worker->loadMissing('documents');
        $missing = [];
        if (! $worker->birth_date) {
            $missing[] = 'birth date';
        }
        if (! $worker->phone) {
            $missing[] = 'phone number';
        }
        if (! $worker->address) {
            $missing[] = 'complete address';
        }
        if (! $worker->resume_path) {
            $missing[] = 'resume / CV';
        }
        if (! $worker->education) {
            $missing[] = 'educational attainment';
        }
        if (! $worker->skills) {
            $missing[] = 'skills';
        }
        if (! $worker->position_skill) {
            $missing[] = 'position / skill';
        }
        if (! $worker->availability) {
            $missing[] = 'availability';
        }
        if (! $worker->consent_given) {
            $missing[] = 'agency consent';
        }
        if ($missing) {
            return back()->with('error', 'Required checks incomplete: ' . implode(', ', $missing) . '.');
        }
        if ($worker->documents->where('status', WorkerDocument::STATUS_REJECTED)->isNotEmpty()) {
            return back()->with('error', 'A supporting document was Rejected — ask the agency to re-upload before approving.');
        }
        if ($worker->documents->where('status', WorkerDocument::STATUS_EXPIRED)->isNotEmpty()) {
            return back()->with('error', 'A supporting document is Expired — ask the agency to renew it before approving.');
        }
        if ($worker->clearance_expired) {
            return back()->with('error', 'Clearance / medical has expired — cannot approve.');
        }

        $worker->update(['status' => Worker::STATUS_AVAILABLE]);
        $this->log($worker, 'worker_approved', "Worker {$worker->code} approved by HR (Available).", null);

        return back()->with('success', "Worker {$worker->name} approved.");
    }

    /** HR rejects an agency-added worker: Pending → Inactive (records kept). */
    public function rejectPending(Request $request, Worker $worker)
    {
        $request->validate(['reason' => 'required|string|max:2000']);

        if (! in_array($worker->status, [Worker::STATUS_PENDING, Worker::STATUS_CORRECTION], true)) {
            return back()->with('error', 'Only workers pending HR approval can be rejected.');
        }

        $worker->update(['status' => Worker::STATUS_INACTIVE]);
        $this->log($worker, 'worker_rejected', "Worker {$worker->code} rejected by HR.", $request->reason);

        return back()->with('success', "Worker {$worker->name} rejected.");
    }

    /**
     * HR sends a submission back to the agency for correction or completion:
     * Pending → For Correction with a note. HR may propose overview edits
     * inline — those are NOT saved; the old → new diff is appended to the
     * note + activity so the agency portal applies the changes itself.
     */
    public function requestChanges(Request $request, Worker $worker)
    {
        $request->validate(['message' => 'required|string|max:2000']);

        if ($worker->status !== Worker::STATUS_PENDING) {
            return back()->with('error', 'Only workers pending HR approval can be sent back for correction.');
        }

        // Optional HR-proposed overview values (sent when the Detail overview
        // is edited inside the Request-changes flow). Validated like update
        // but never saved — agency applies them. Skipped when only a message
        // is posted (legacy callers).
        $workerKeys = ['name', 'agency_id', 'birth_date', 'gender', 'phone', 'email', 'address', 'education', 'work_experience', 'skills', 'trainings_certifications', 'position_skill', 'years_experience', 'proposed_department', 'availability', 'daily_rate', 'employment_arrangement', 'gov_id_type', 'gov_id_number', 'clearance_expiry', 'emergency_name', 'emergency_relationship', 'emergency_phone', 'code'];
        $hasWorkerFields = collect($workerKeys)->some(fn ($k) => $request->has($k));
        $data = $hasWorkerFields ? $this->validated($request, $worker->id) : [];
        unset($data['agency_id'], $data['code'], $data['resume']);

        $labels = [
            'name' => 'Full name', 'birth_date' => 'Birth date', 'gender' => 'Gender',
            'phone' => 'Phone', 'email' => 'Email', 'address' => 'Address',
            'education' => 'Education', 'work_experience' => 'Experience', 'skills' => 'Skills',
            'trainings_certifications' => 'Training', 'position_skill' => 'Position',
            'years_experience' => 'Years exp.', 'proposed_department' => 'Department',
            'availability' => 'Availability', 'daily_rate' => 'Daily rate',
            'employment_arrangement' => 'Arrangement', 'gov_id_type' => 'Gov ID type',
            'gov_id_number' => 'Gov ID no.', 'clearance_expiry' => 'Clearance expiry',
            'emergency_name' => 'Emergency name', 'emergency_relationship' => 'Emergency relation',
            'emergency_phone' => 'Emergency phone',
        ];
        $fmt = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (is_bool($v) ? ($v ? 'Yes' : 'No') : (string) ($v ?? ''));
        $diffs = [];
        foreach ($labels as $key => $label) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $old = $fmt($worker->getAttribute($key));
            $new = $fmt($data[$key]);
            if ($old !== $new) {
                $diffs[] = "{$label}: " . ($old === '' ? '—' : $old) . " → " . ($new === '' ? '—' : $new);
            }
        }

        $note = $request->message;
        if ($diffs) {
            $note .= "\n\nHR requested changes (agency to apply):\n- " . implode("\n- ", $diffs);
        }

        // Proposal only — worker data stays untouched so the agency portal
        // applies the corrections itself.
        $worker->update([
            'status' => Worker::STATUS_CORRECTION,
            'pending_note' => $note,
        ]);
        $this->log($worker, 'worker_correction_requested', "Worker {$worker->code} sent back for correction by HR." . ($diffs ? ' Requested: ' . implode('; ', $diffs) : ''), $note);

        return back()->with('success', "Correction requested for {$worker->name}. The agency will apply your note" . ($diffs ? ' and the ' . count($diffs) . ' requested field change(s).' : '.'));
    }

    /** HR approves an agency-requested rate change (applies the new rate). */
    public function approveRate(WorkerRateRequest $rateRequest)
    {
        if ($rateRequest->status !== WorkerRateRequest::STATUS_PENDING) {
            return back()->with('error', 'This rate request is no longer pending.');
        }

        $rateRequest->update(['status' => WorkerRateRequest::STATUS_APPROVED, 'reviewed_by_name' => auth()->user()?->name]);
        $worker = $rateRequest->worker;
        $worker->update(['daily_rate' => $rateRequest->new_rate]);

        AgencyActivity::create([
            'agency_id' => $worker->agency_id,
            'worker_id' => $worker->id,
            'action' => 'rate_approved',
            'details' => "Rate for {$worker->code} changed {$rateRequest->old_rate} → {$rateRequest->new_rate} (approved).",
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
        ]);

        return back()->with('success', "Rate approved for {$worker->name}.");
    }

    public function rejectRate(Request $request, WorkerRateRequest $rateRequest)
    {
        $request->validate(['reason' => 'required|string|max:2000']);

        if ($rateRequest->status !== WorkerRateRequest::STATUS_PENDING) {
            return back()->with('error', 'This rate request is no longer pending.');
        }

        $rateRequest->update([
            'status' => WorkerRateRequest::STATUS_REJECTED,
            'review_reason' => $request->reason,
            'reviewed_by_name' => auth()->user()?->name,
        ]);

        return back()->with('success', 'Rate change rejected.');
    }

    /**
     * HR verifies a supporting file: Pending | Verified | Rejected | Expired.
     */
    public function verifyDocument(Request $request, WorkerDocument $document)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(WorkerDocument::STATUSES)],
            'review_note' => 'nullable|string|max:2000',
        ]);

        $document->update([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'verified_by_name' => auth()->user()?->name,
            'verified_at' => now(),
        ]);

        $worker = $document->worker;
        AgencyActivity::create([
            'agency_id' => $worker->agency_id,
            'worker_id' => $worker->id,
            'action' => 'document_' . strtolower($data['status']),
            'details' => "Document '{$document->label}' ({$document->doc_type}) marked {$data['status']} by HR.",
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
            'reason' => $data['review_note'] ?? null,
        ]);

        return back()->with('success', "Document marked as {$data['status']}.");
    }

    // ------------------------------------------------------------------
    // Payloads
    // ------------------------------------------------------------------
    protected function row(Worker $worker): array
    {
        $active = $worker->relationLoaded('activeAssignments')
            ? $worker->activeAssignments->first()
            : $worker->activeAssignment()->with('request:id,code,department')->first();

        return [
            'id' => $worker->id,
            'code' => $worker->code,
            'name' => $worker->name,
            'agency' => $worker->agency?->name,
            'agency_id' => $worker->agency_id,
            'position_skill' => $worker->position_skill,
            'current_assignment' => $active?->request
                ? $active->request->code . ' · ' . ($active->request->department ?? '')
                : null,
            'status' => $worker->status,
            'clearance_expiry' => optional($worker->clearance_expiry)->toDateString(),
            'clearance_expired' => (bool) $worker->clearance_expired,
            'expiring_soon' => (bool) $worker->clearance_expiring_soon,
        ];
    }

    protected function detail(Worker $worker): array
    {
        $worker->loadMissing('documents');
        $assignments = $worker->assignments->sortByDesc('assigned_at')->map(fn ($a) => [
            'id' => $a->id,
            'request_id' => $a->request_id,
            'request_code' => $a->request?->code,
            'department' => $a->request?->department,
            'assigned_at' => optional($a->assigned_at)->format('M d, Y'),
            'released_at' => optional($a->released_at)->format('M d, Y'),
            'assigned_by' => $a->assigned_by_name,
            'active' => $a->released_at === null,
        ])->values()->all();

        $activities = AgencyActivity::where('worker_id', $worker->id)
            ->latest()->take(20)->get()->map(fn ($a) => [
                'id' => $a->id,
                'date' => optional($a->created_at)->format('M d, Y h:i A'),
                'action' => $a->action,
                'details' => $a->details,
                'actor' => $a->actor_name ?? ('#' . $a->actor_id),
                'reason' => $a->reason,
            ])->values()->all();

        return [
            'id' => $worker->id,
            'code' => $worker->code,
            'name' => $worker->name,
            'agency_id' => $worker->agency_id,
            'agency' => $worker->agency?->name,
            'agency_code' => $worker->agency?->code,
            'agency_status' => $worker->agency?->status,
            'birth_date' => optional($worker->birth_date)->toDateString(),
            'gender' => $worker->gender,
            'phone' => $worker->phone,
            'email' => $worker->email,
            'address' => $worker->address,
            'resume_url' => $worker->resume_path ? Storage::url($worker->resume_path) : null,
            'resume_original_name' => $worker->resume_original_name,
            'education' => $worker->education,
            'work_experience' => $worker->work_experience,
            'skills' => $worker->skills,
            'trainings_certifications' => $worker->trainings_certifications,
            'position_skill' => $worker->position_skill,
            'years_experience' => $worker->years_experience,
            'proposed_department' => $worker->proposed_department,
            'availability' => $worker->availability,
            'daily_rate' => $worker->daily_rate,
            'employment_arrangement' => $worker->employment_arrangement,
            'gov_id_type' => $worker->gov_id_type,
            'gov_id_number' => $worker->gov_id_number,
            'clearance_expiry' => optional($worker->clearance_expiry)->toDateString(),
            'clearance_expired' => (bool) $worker->clearance_expired,
            'emergency_name' => $worker->emergency_name,
            'emergency_relationship' => $worker->emergency_relationship,
            'emergency_phone' => $worker->emergency_phone,
            'consent_given' => (bool) $worker->consent_given,
            'consent_at' => optional($worker->consent_at)->format('M d, Y h:i A'),
            'privacy_notice_version' => $worker->privacy_notice_version,
            'status' => $worker->status,
            'blacklist_reason' => $worker->blacklist_reason,
            'pending_note' => $worker->pending_note,
            'updated_at' => optional($worker->updated_at)->format('M d, Y h:i A'),
            'last_agency_update' => AgencyActivity::where('worker_id', $worker->id)
                ->whereIn('action', ['worker_updated', 'worker_resubmitted', 'worker_created', 'worker_document_removed'])
                ->latest()->first()?->created_at?->format('M d, Y h:i A'),
            'documents' => $worker->documents->map(fn ($d) => [
                'id' => $d->id,
                'doc_type' => $d->doc_type,
                'doc_type_label' => WorkerDocument::TYPES[$d->doc_type] ?? $d->doc_type,
                'label' => $d->label,
                'original_name' => $d->original_name,
                'url' => Storage::url($d->file_path),
                'mime' => $d->mime,
                'issue_date' => optional($d->issue_date)->toDateString(),
                'expiry_date' => optional($d->expiry_date)->toDateString(),
                'status' => $d->status,
                'review_note' => $d->review_note,
                'verified_by' => $d->verified_by_name,
                'verified_at' => optional($d->verified_at)->format('M d, Y h:i A'),
            ])->values()->all(),
            'document_statuses' => WorkerDocument::STATUSES,
            'assignments' => $assignments,
            'activities' => $activities,
            // Attendance history (latest 30).
            'attendance_history' => \App\Models\Hrm\Attendance::with('request:id,code')
                ->where('worker_id', $worker->id)
                ->orderByDesc('date')->take(30)->get()->map(fn ($a) => [
                    'id' => $a->id,
                    'date' => $a->date->toDateString(),
                    'request_code' => $a->request?->code,
                    'status' => $a->status,
                    'hours' => $a->hours,
                    'overtime_hours' => $a->overtime_hours,
                    'approval' => $a->approval,
                ])->values()->all(),
        ];
    }

    protected function rules(?int $ignoreId = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:30', Rule::unique('workers', 'code')->ignore($ignoreId)],
            'agency_id' => 'required|exists:agencies,id',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:60',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:2000',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'education' => 'nullable|string|max:2000',
            'work_experience' => 'nullable|string|max:4000',
            'skills' => 'nullable|string|max:4000',
            'trainings_certifications' => 'nullable|string|max:4000',
            'position_skill' => 'nullable|string|max:255',
            'years_experience' => 'nullable|numeric|min:0|max:60',
            'proposed_department' => 'nullable|string|max:255',
            'availability' => 'nullable|string|max:255',
            'daily_rate' => 'nullable|numeric|min:0',
            'employment_arrangement' => 'nullable|string|max:100',
            'gov_id_type' => 'nullable|string|max:60',
            'gov_id_number' => ['nullable', 'string', 'max:100', Rule::unique('workers', 'gov_id_number')->ignore($ignoreId)],
            'clearance_expiry' => 'nullable|date',
            'emergency_name' => 'nullable|string|max:255',
            'emergency_relationship' => 'nullable|string|max:60',
            'emergency_phone' => 'nullable|string|max:60',
        ];
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        // Blanks become null so the optional unique (gov ID) never collides
        // on empty strings; the DB unique index backs the rule.
        foreach (['code', 'gov_id_number'] as $key) {
            if ($request->get($key) === '') {
                $request->merge([$key => null]);
            }
        }

        return $request->validate($this->rules($ignoreId));
    }

    protected static function nextCode(): string
    {
        $next = (int) (Worker::max('id') ?? 0) + 1;
        do {
            $code = 'WRK-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Worker::where('code', $code)->exists());

        return $code;
    }

    protected function log(Worker $worker, string $action, ?string $details, ?string $reason): void
    {
        AgencyActivity::create([
            'agency_id' => $worker->agency_id,
            'worker_id' => $worker->id,
            'action' => $action,
            'details' => $details,
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
            'reason' => $reason,
        ]);
    }
}
