<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\Worker;
use App\Models\Hrm\WorkerDocument;
use App\Support\NotifiesHr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Agency portal: own workers only. Add/Edit allowed, but:
 * - new workers enter as Pending HR approval (never directly Available),
 * - status transitions (Deployed/Blacklisted/...) are HR-only,
 * - daily-rate edits become rate-change requests for HR approval.
 * Every query is scoped to the logged-in user's agency.
 *
 * Submission follows Sections A-F: personal info, resume/qualifications,
 * work/deployment, government/supporting docs, emergency contact,
 * submission + consent. HR verifies each supporting file as
 * Pending | Verified | Rejected | Expired before approval.
 */
class WorkerPortalController extends Controller
{
    public const PRIVACY_NOTICE_VERSION = 'v2026-10-01';

    private function agencyId(Request $request): int
    {
        return (int) $request->user('agency')->agency_id;
    }

    public function index(Request $request)
    {
        $agencyId = $this->agencyId($request);
        $q = Worker::where('agency_id', $agencyId)->with('activeAssignments.request:id,code,department');

        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%"));
        }
        if (($status = $request->get('status')) && $status !== 'All') {
            $q->where('status', $status);
        }

        $paginated = $q->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Agency/Workers/Index', [
            'workers' => collect($paginated->items())->map(fn ($w) => [
                'id' => $w->id,
                'code' => $w->code,
                'name' => $w->name,
                'position_skill' => $w->position_skill,
                'daily_rate' => $w->daily_rate,
                'status' => $w->status,
                'pending_note' => $w->pending_note,
                'clearance_expiry' => optional($w->clearance_expiry)->toDateString(),
                'current_assignment' => ($a = $w->activeAssignments->first()) && $a->request
                    ? $a->request->code : null,
            ])->values()->all(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Agency/Workers/Form', [
            'worker' => null,
            'privacy_notice_version' => self::PRIVACY_NOTICE_VERSION,
            'document_types' => WorkerDocument::TYPES,
            'document_statuses' => WorkerDocument::STATUSES,
            'departments' => \App\Models\Hrm\HrmDepartment::whereNull('archived_at')->orderBy('name')->get(['id', 'code', 'name']),
            'positions' => \App\Models\Hrm\HrmPosition::whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'department_id']),
        ]);
    }

    public function store(Request $request)
    {
        $agencyId = $this->agencyId($request);
        foreach (['code', 'gov_id_number'] as $key) {
            if ($request->get($key) === '') {
                $request->merge([$key => null]);
            }
        }
        $data = $request->validate($this->rules());

        if (empty($data['code'])) {
            $data['code'] = self::nextCode();
        }

        // B. Resume / CV (PDF or DOCX, required for a full profile).
        if ($request->hasFile('resume')) {
            $file = $request->file('resume');
            $data['resume_path'] = $file->store('workers/resumes', 'public');
            $data['resume_original_name'] = $file->getClientOriginalName();
        }

        $worker = Worker::create($data + [
            'agency_id' => $agencyId,
            // Agency-added workers wait for HR approval — never Available yet.
            'status' => Worker::STATUS_PENDING,
            'consent_at' => now(),
            'privacy_notice_version' => self::PRIVACY_NOTICE_VERSION,
        ]);

        // Mirror the resume as a verifiable supporting document.
        if (! empty($data['resume_path'])) {
            $worker->documents()->create([
                'doc_type' => 'resume',
                'label' => 'Resume / CV',
                'file_path' => $data['resume_path'],
                'original_name' => $data['resume_original_name'] ?? null,
                'mime' => $request->hasFile('resume') ? $request->file('resume')->getClientMimeType() : null,
                'size' => $request->hasFile('resume') ? $request->file('resume')->getSize() : null,
                'status' => WorkerDocument::STATUS_PENDING,
            ]);
        }

        $this->storeSupportingDocuments($request, $worker);

        $this->log($agencyId, $worker->id, null, 'worker_created', "Worker {$worker->code} ({$worker->name}) added by {$request->user('agency')->name} — pending HR approval.", null, $request);
        NotifiesHr::push(
            "Agency worker pending approval: {$worker->name}",
            "{$request->user('agency')->name} added {$worker->code} and awaits HR approval.",
            'hrm.workers.index'
        );

        return redirect()->route('agency.workers.index')
            ->with('success', "Worker {$worker->name} submitted. It becomes Available once HR approves it.");
    }

    public function edit(Request $request, Worker $worker)
    {
        abort_unless((int) $worker->agency_id === $this->agencyId($request), 404);
        $worker->loadMissing('documents');

        return Inertia::render('Agency/Workers/Form', [
            'worker' => $this->payload($worker),
            'privacy_notice_version' => self::PRIVACY_NOTICE_VERSION,
            'document_types' => WorkerDocument::TYPES,
            'document_statuses' => WorkerDocument::STATUSES,
            'departments' => \App\Models\Hrm\HrmDepartment::whereNull('archived_at')->orderBy('name')->get(['id', 'code', 'name']),
            'positions' => \App\Models\Hrm\HrmPosition::whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'department_id']),
        ]);
    }

    public function update(Request $request, Worker $worker)
    {
        abort_unless((int) $worker->agency_id === $this->agencyId($request), 404);

        foreach (['code', 'gov_id_number'] as $key) {
            if ($request->get($key) === '') {
                $request->merge([$key => null]);
            }
        }
        $data = $request->validate($this->rules($worker->id, true));
        if (empty($data['code'])) {
            $data['code'] = $worker->code;
        }
        unset($data['agency_id']);

        if ($request->hasFile('resume')) {
            if ($worker->resume_path && Storage::disk('public')->exists($worker->resume_path)) {
                Storage::disk('public')->delete($worker->resume_path);
            }
            $file = $request->file('resume');
            $data['resume_path'] = $file->store('workers/resumes', 'public');
            $data['resume_original_name'] = $file->getClientOriginalName();
        }

        // Agency edits apply directly — including daily_rate. No HR approval
        // queue for rate changes (pending workers are freely editable).

        // Re-confirming consent refreshes the consent timestamp.
        if (! empty($data['consent_given'])) {
            $data['consent_at'] = now();
            $data['privacy_notice_version'] = self::PRIVACY_NOTICE_VERSION;
        }

        // Resubmitting after an HR correction request returns the worker to
        // Pending HR Approval so HR can re-review it.
        $resubmitted = $worker->status === Worker::STATUS_CORRECTION;
        if ($resubmitted) {
            $data['status'] = Worker::STATUS_PENDING;
        }

        $worker->update($data);

        if (! empty($data['resume_path'])) {
            $worker->documents()->updateOrCreate(
                ['doc_type' => 'resume'],
                [
                    'label' => 'Resume / CV',
                    'file_path' => $data['resume_path'],
                    'original_name' => $data['resume_original_name'] ?? null,
                    'mime' => $request->hasFile('resume') ? $request->file('resume')->getClientMimeType() : null,
                    'size' => $request->hasFile('resume') ? $request->file('resume')->getSize() : null,
                    'status' => WorkerDocument::STATUS_PENDING,
                    'review_note' => null,
                    'verified_by_name' => null,
                    'verified_at' => null,
                ]
            );
        }

        $this->storeSupportingDocuments($request, $worker);
        if ($resubmitted) {
            $this->log($this->agencyId($request), $worker->id, null, 'worker_resubmitted', "Worker {$worker->code} corrected and resubmitted by {$request->user('agency')->name} — pending HR approval.", null, $request);
            NotifiesHr::push(
                "Correction completed — ready for HR review: {$worker->name} ({$worker->code})",
                "{$request->user('agency')->name} completed your correction request for {$worker->code} — changes applied, request done, pending HR approval.",
                'hrm.workers.index'
            );
        } else {
            $this->log($this->agencyId($request), $worker->id, null, 'worker_updated', "Worker {$worker->code} updated by {$request->user('agency')->name}.", null, $request);
        }

        return redirect()->route('agency.workers.index')
            ->with('success', $resubmitted ? "Worker {$worker->name} resubmitted for HR approval." : "Worker {$worker->name} updated.");
    }

    /**
     * Agency removes an uploaded file (X button). SOFT DELETE ONLY:
     * sets deleted_at — the row stays in the database and no file
     * is removed from storage, so HR can still recover it.
     */
    public function destroyDocument(Request $request, Worker $worker, WorkerDocument $document)
    {
        abort_unless((int) $worker->agency_id === $this->agencyId($request), 404);
        abort_unless((int) $document->worker_id === (int) $worker->id, 404);

        $label = $document->original_name ?: ($document->label ?: $document->doc_type);
        $isResume = $document->doc_type === 'resume';
        $document->delete(); // soft delete (deleted_at)

        if ($isResume) {
            // Remove any sibling resume mirrors too (re-uploads), then clear
            // the workers-table pointer so the form no longer shows it.
            // Files stay on disk and rows stay in DB (deleted_at) — recoverable.
            $worker->documents()->where('doc_type', 'resume')->delete();
            $worker->update([
                'resume_path' => null,
                'resume_original_name' => null,
            ]);
        }

        $this->log($this->agencyId($request), $worker->id, null, 'worker_document_removed', "File {$label} removed by {$request->user('agency')->name} (soft-deleted, recoverable).", null, $request);

        return back()->with('success', "File {$label} removed. It stays in the database and can be recovered.");
    }

    protected function payload(Worker $worker): array
    {
        return [
            'id' => $worker->id,
            'code' => $worker->code,
            'name' => $worker->name,
            'birth_date' => optional($worker->birth_date)->toDateString(),
            'gender' => $worker->gender,
            'phone' => $worker->phone,
            'email' => $worker->email,
            'address' => $worker->address,
            'resume_path' => $worker->resume_path,
            'resume_original_name' => $worker->resume_original_name,
            'resume_url' => $worker->resume_path ? Storage::url($worker->resume_path) : null,
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
            'emergency_name' => $worker->emergency_name,
            'emergency_relationship' => $worker->emergency_relationship,
            'emergency_phone' => $worker->emergency_phone,
            'consent_given' => (bool) $worker->consent_given,
            'status' => $worker->status,
            'pending_note' => $worker->pending_note,
            'documents' => $worker->documents->map(fn ($d) => [
                'id' => $d->id,
                'doc_type' => $d->doc_type,
                'label' => $d->label,
                'original_name' => $d->original_name,
                'url' => Storage::url($d->file_path),
                'issue_date' => optional($d->issue_date)->toDateString(),
                'expiry_date' => optional($d->expiry_date)->toDateString(),
                'status' => $d->status,
                'review_note' => $d->review_note,
            ])->values()->all(),
        ];
    }

    protected function storeSupportingDocuments(Request $request, Worker $worker): void
    {
        /** @var array<\Illuminate\Http\UploadedFile> $files */
        $files = $request->file('documents', []);
        if (! is_array($files)) {
            $files = [$files];
        }
        $types = (array) $request->get('document_types', []);
        $labels = (array) $request->get('document_labels', []);
        $starts = (array) $request->get('document_starts', []);
        $expiries = (array) $request->get('document_expiries', []);

        foreach (array_values(array_filter($files)) as $i => $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $docType = $types[$i] ?? 'other';
            if (! array_key_exists($docType, WorkerDocument::TYPES)) {
                $docType = 'other';
            }
            $worker->documents()->create([
                'doc_type' => $docType,
                'label' => $labels[$i] ?? (WorkerDocument::TYPES[$docType] ?? null),
                'file_path' => $file->store('workers/documents', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'issue_date' => $starts[$i] ?: null,
                'expiry_date' => $expiries[$i] ?: null,
                'status' => WorkerDocument::STATUS_PENDING,
            ]);
        }
    }

    protected function rules(?int $ignoreId = null, bool $isUpdate = false): array
    {
        // Minimum submission: name, birth date, phone, address, resume,
        // education, skills, position/skill, availability, consent.
        // Everything else is optional initially and completed during HR
        // verification (gov IDs, clearances, medical, emergency contact,
        // department, rate, arrangement collected as applicable).
        return [
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:30', Rule::unique('workers', 'code')->ignore($ignoreId)],
            'birth_date' => 'required|date|before:today',
            'gender' => 'nullable|string|max:20',
            'phone' => ['required', 'string', 'max:60', 'regex:/^(09\d{2}\s?\d{3}\s?\d{4}|09\d{9}|\+63\d{10})$/'],
            'email' => 'nullable|email|max:255',
            'address' => 'required|string|max:2000',

            'resume' => [$isUpdate ? 'nullable' : 'required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'education' => 'required|string|max:2000',
            'work_experience' => 'nullable|string|max:4000',
            'skills' => 'required|string|max:4000',
            'trainings_certifications' => 'nullable|string|max:4000',

            'position_skill' => 'required|string|max:255',
            'years_experience' => 'nullable|numeric|min:0|max:60',
            'proposed_department' => 'nullable|string|max:255',
            'availability' => 'required|date',
            'daily_rate' => 'nullable|numeric|min:0',
            'employment_arrangement' => 'nullable|string|max:100',

            'gov_id_type' => 'nullable|string|max:60',
            'gov_id_number' => ['nullable', 'string', 'max:100', Rule::unique('workers', 'gov_id_number')->ignore($ignoreId)],
            'clearance_expiry' => 'nullable|date',
            'documents' => 'nullable|array|max:10',
            'documents.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'document_types' => 'nullable|array',
            'document_types.*' => 'nullable|string|max:40',
            'document_labels' => 'nullable|array',
            'document_labels.*' => 'nullable|string|max:255',
            'document_starts' => 'nullable|array',
            'document_starts.*' => 'nullable|date',
            'document_expiries' => 'nullable|array',
            'document_expiries.*' => 'nullable|date',

            'emergency_name' => 'nullable|string|max:255',
            'emergency_relationship' => 'nullable|string|max:60',
            'emergency_phone' => 'nullable|string|max:60',

            'consent_given' => $isUpdate ? 'nullable|boolean' : 'accepted',
        ];
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

    protected function log(int $agencyId, ?int $workerId, ?int $requestId, string $action, ?string $details, ?string $reason, Request $request): void
    {
        AgencyActivity::create([
            'agency_id' => $agencyId,
            'worker_id' => $workerId,
            'request_id' => $requestId,
            'action' => $action,
            'details' => $details,
            'actor_id' => null,
            'actor_name' => $request->user('agency')->name . ' (agency)',
            'reason' => $reason,
        ]);
    }
}
