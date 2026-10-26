<?php

namespace App\Http\Controllers\Hrm\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\ApplicantJobApplication;
use App\Models\Hrm\ApplicantStatusHistory;
use App\Models\Hrm\HrmApplicationScreening;
use App\Models\Hrm\HrmJobPosting;
use App\Services\Hrm\ApplicantApplicationHoldService;
use App\Support\NotifiesApplicant;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        // Only applicants who actually applied to a job posting are listed —
        // applicants with no job application rows (never applied) are hidden.
        $q = Applicant::with(['jobPosting', 'jobApplications.jobPosting', 'jobApplications.heldByApplication.jobPosting'])
            ->whereHas('jobApplications');
        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        if ($st = $request->get('status')) {
            if (strtolower((string) $st) === 'on hold' && ApplicantApplicationHoldService::columnsReady()) {
                $q->whereHas('jobApplications', fn ($j) => $j->where('is_on_hold', true));
            } else {
                // Match the applicant status OR any of their per-job application statuses.
                $q->where(fn ($w) => $w->where('status', $st)
                    ->orWhereHas('jobApplications', fn ($j) => $j->where('status', $st)));
            }
        }

        $paginated = $q->latest()->paginate(15)->withQueryString();
        // One card per job applied: an applicant with 2+ portal job applications
        // produces 2+ rows (each with its own job, status and date) so HR sees
        // every application. Hired rows live in History — they are excluded
        // here so the main table only shows in-pipeline applications.
        $applications = collect($paginated->items())->filter(fn ($a) => $a !== null)
            ->flatMap(fn ($a) => $this->mapApplicationRow($a))
            ->reject(fn ($row) => ($row['status'] ?? null) === 'Hired')
            ->values()->all();

        return Inertia::render('Dashboard/HRM_NEW/Applications', [
            'applications' => $applications,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'metrics' => [
                'total' => Applicant::whereHas('jobApplications')->count(),
                'pending' => Applicant::whereHas('jobApplications')->whereIn('status', ['pending', 'Submitted'])->count(),
                'screening' => Applicant::whereHas('jobApplications')->where('status', 'Screening')->count(),
                'interview' => Applicant::whereHas('jobApplications')->where('status', 'Interview')->count(),
            ],
            'filters' => $request->only(['search', 'status', 'position']),
            'filterOptions' => ['statuses' => ['Submitted', 'Screening', 'Shortlisted', 'Interview', 'Offered', 'Rejected']],
            // Position options for the Add/Edit Application modal.
            'jobPostings' => HrmJobPosting::whereNull('archived_at')
                ->where('status', 'Published')
                ->orderBy('title')
                ->select('id', 'title')
                ->get(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    /**
     * Hired applicants for the History panel — same card shape as the main
     * list. These rows are excluded from the main table.
     */
    public function hired()
    {
        $apps = Applicant::with(['jobPosting', 'jobApplications.jobPosting', 'jobApplications.heldByApplication.jobPosting'])
            ->where(fn ($q) => $q->where('status', 'Hired')
                ->orWhereHas('jobApplications', fn ($j) => $j->where('status', 'Hired')))
            ->latest()->take(100)->get();

        $rows = $apps->flatMap(fn ($a) => $this->mapApplicationRow($a))
            ->filter(fn ($row) => ($row['status'] ?? null) === 'Hired')
            ->values()->all();

        return response()->json(['data' => $rows]);
    }

    /**
     * One card per job applied, in the exact shape the Applications cards
     * (main table and History) render.
     */
    private function mapApplicationRow($a): array
    {
        $photoPath = $a->profile_photo_path ?: $a->image;
        $photoUrl = $photoPath ? Storage::url($photoPath) : null;
        $base = $a->toArray();
        unset($base['job_applications']);
        $base['profile_photo_url'] = $photoUrl;
        // The cards read `phone` — the table stores phone_number / contact_number.
        $base['phone'] = $a->phone_number ?: $a->contact_number;
        // Attach the linked HRM_NEW job posting so the position filter works.
        $base['job_posting'] = $a->jobPosting ? ['id' => $a->jobPosting->id, 'title' => $a->jobPosting->title] : null;

        $jas = $a->jobApplications ?? collect();
        if ($jas->isEmpty()) {
            return [];
        }

        return $jas->map(function ($ja) use ($base) {
            $row = $base;
            $row['job_application_id'] = $ja->id;
            $row['status'] = $ja->status;
            $row['cover_letter'] = $ja->cover_letter ?? ($base['cover_letter'] ?? null);
            $row['application_date'] = optional($ja->created_at)?->toDateString();
            $row['created_at'] = optional($ja->created_at)?->toISOString();
            $row['updated_at'] = optional($ja->updated_at)?->toISOString();
            $row['job_posting_id'] = $ja->job_posting_id;
            $row['is_on_hold'] = (bool) $ja->is_on_hold;
            $row['hold_reason'] = $ja->hold_reason;
            $row['previous_status'] = $ja->previous_status;
            $row['held_by_application_id'] = $ja->held_by_application_id;
            $row['held_by'] = $ja->heldByApplication ? [
                'id' => $ja->heldByApplication->id,
                'status' => $ja->heldByApplication->status,
                'job_posting_id' => $ja->heldByApplication->job_posting_id,
                'job_title' => $ja->heldByApplication->jobPosting?->title,
            ] : null;
            $jp = $ja->jobPosting;
            if ($jp) {
                $row['job_posting'] = ['id' => $jp->id, 'title' => $jp->title];
            }
            return $row;
        })->all();
    }

    public function profile(Request $request, Applicant $application)
    {
        // Rich payload shared with the APPLICANTS module (HRM-connected).
        $payload = app(\App\Services\Applicants\ApplicantProfileService::class)->for($application);

        // When opened from a specific job-application card, show that
        // application's own status / cover letter / dates / posting so each
        // of the applicant's applications displays its own context.
        if ($jaId = $request->get('job_application_id')) {
            $ja = ApplicantJobApplication::with('jobPosting.employmentType', 'jobPosting.position')
                ->where('applicant_id', $application->id)->find($jaId);
            if ($ja) {
                $payload['application']['job_application_id'] = $ja->id;
                $payload['application']['status'] = $ja->status;
                $payload['application']['screening'] = $application->screenings()
                    ->where('job_posting_id', $ja->job_posting_id)
                    ->latest()->first();
                // This job's own resume — NOT the applicant's first library
                // file — so each application card shows the resume uploaded
                // for that specific job.
                $resumeDoc = $ja->resolveResumeDocument();
                if ($resumeDoc) {
                    $payload['application']['resume_path'] = $resumeDoc->file_path;
                    $payload['application']['resume_name'] = $resumeDoc->original_name;
                } elseif ($ja->resume_path) {
                    $payload['application']['resume_path'] = $ja->resume_path;
                    $payload['application']['resume_name'] = basename($ja->resume_path);
                } else {
                    $payload['application']['resume_path'] = null;
                    $payload['application']['resume_name'] = null;
                }
                $payload['application']['cover_letter'] = $ja->cover_letter;
                $payload['application']['availability_date'] = optional($ja->availability_date)?->toDateString();
                $payload['application']['notice_period'] = $ja->notice_period;
                $payload['application']['application_date'] = optional($ja->created_at)?->toDateString();
                $payload['application']['is_on_hold'] = (bool) $ja->is_on_hold;
                $payload['application']['hold_reason'] = $ja->hold_reason;
                $payload['application']['previous_status'] = $ja->previous_status;
                $payload['application']['held_by_application_id'] = $ja->held_by_application_id;
                if ($ja->jobPosting) {
                    $payload['job_posting'] = [
                        'id' => $ja->jobPosting->id,
                        'title' => $ja->jobPosting->title,
                        'posting_id' => $ja->jobPosting->posting_id,
                        'department' => $ja->jobPosting->department,
                        'employment_type' => $ja->jobPosting->employmentType?->name,
                    ];
                    if ($pos = $ja->jobPosting->position) {
                        $payload['position'] = array_merge($payload['position'] ?? [], [
                            'name' => $pos->name,
                            'description' => $pos->description ?: $ja->jobPosting->recruitment_notes,
                            'responsibilities' => $pos->responsibilities,
                            'qualifications' => $pos->qualifications,
                            'required_skills' => $pos->required_skills,
                        ]);
                    }
                }
            }
        }

        return response()->json($payload);
    }

    public function screeningPage(Request $request, Applicant $application)
    {
        $ja = $this->targetedJobApplication($request, $application);
        if ($ja?->is_on_hold) {
            abort(404);
        }

        return Inertia::render('Dashboard/HRM_NEW/ApplicationScreening', [
            'applicationId' => $application->id,
            'jobApplicationId' => $request->integer('job_application_id') ?: null,
        ]);
    }

    public function startScreening(Request $request, Applicant $application)
    {
        $ja = $this->targetedJobApplication($request, $application);
        if ($ja) {
            if ($ja->is_on_hold) {
                return response()->json(['message' => ApplicantApplicationHoldService::HOLD_MESSAGE], 422);
            }
            if (in_array($ja->status, ['Withdrawn', 'Rejected', 'Hired'])) {
                return response()->json(['message' => 'This application is already ' . strtolower($ja->status) . ' and cannot be screened.'], 422);
            }
            $ja->update(['status' => 'Screening']);
            $this->refreshApplicantSummary($application);
        } else {
            $application->update(['status' => 'Screening']);
            ApplicantJobApplication::where('applicant_id', $application->id)
                ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
                ->notOnHold()
                ->update(['status' => 'Screening']);
        }

        return response()->json(['ok' => true, 'status' => 'Screening']);
    }

    public function saveScreeningDraft(Request $request, Applicant $application)
    {
        $data = $request->validate([
            'resume_reviewed' => 'nullable|boolean',
            'education_verified' => 'nullable|boolean',
            'work_experience_reviewed' => 'nullable|boolean',
            'skills_reviewed' => 'nullable|boolean',
            'qualifications_met' => 'nullable|boolean',
            'applicant_info_reviewed' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'job_application_id' => 'nullable|exists:applicant_job_applications,id',
        ]);

        $ja = $this->targetedJobApplication($request, $application);
        if ($ja?->is_on_hold) {
            return response()->json(['message' => ApplicantApplicationHoldService::HOLD_MESSAGE], 422);
        }

        $jobPostingId = $ja?->job_posting_id ?: $application->job_posting_id;
        $screening = HrmApplicationScreening::where('applicant_id', $application->id)
            ->where('result', 'pending')
            ->where('screened_by', $request->user()->id)
            ->when($jobPostingId, fn ($query) => $query->where('job_posting_id', $jobPostingId))
            ->when(! $jobPostingId, fn ($query) => $query->whereNull('job_posting_id'))
            ->latest()->first();

        $payload = collect([
            'resume_reviewed', 'education_verified', 'work_experience_reviewed',
            'skills_reviewed', 'qualifications_met', 'applicant_info_reviewed', 'notes',
        ])->mapWithKeys(fn ($field) => [$field => $data[$field] ?? ($field === 'notes' ? null : false)])->all();
        $payload['applicant_id'] = $application->id;
        $payload['job_posting_id'] = $jobPostingId;
        $payload['result'] = 'pending';
        $payload['screened_by'] = $request->user()->id;

        if ($screening) {
            $screening->update($payload);
        } else {
            $screening = HrmApplicationScreening::create($payload);
        }

        return response()->json(['ok' => true, 'saved_at' => $screening->updated_at]);
    }

    public function screening(Request $request, Applicant $application)
    {
        $data = $request->validate([
            'resume_reviewed' => 'nullable|boolean',
            'education_verified' => 'nullable|boolean',
            'work_experience_reviewed' => 'nullable|boolean',
            'skills_reviewed' => 'nullable|boolean',
            'qualifications_met' => 'nullable|boolean',
            'applicant_info_reviewed' => 'nullable|boolean',
            'result' => 'required|in:passed,rejected',
            'notes' => 'nullable|string',
            'rejection_reason' => 'required_if:result,rejected|nullable|string',
            'job_posting_id' => 'nullable|exists:hrm_job_postings,id',
            'job_application_id' => 'nullable|exists:applicant_job_applications,id',
        ]);
        $ja = $this->targetedJobApplication($request, $application);
        if ($ja?->is_on_hold) {
            return response()->json(['message' => ApplicantApplicationHoldService::HOLD_MESSAGE], 422);
        }
        $jobPostingId = $ja?->job_posting_id ?: $application->job_posting_id;
        $screening = HrmApplicationScreening::where('applicant_id', $application->id)
            ->where('result', 'pending')
            ->where('screened_by', $request->user()->id)
            ->when($jobPostingId, fn ($query) => $query->where('job_posting_id', $jobPostingId))
            ->when(! $jobPostingId, fn ($query) => $query->whereNull('job_posting_id'))
            ->latest()->first();
        $screeningData = collect([
            'resume_reviewed', 'education_verified', 'work_experience_reviewed',
            'skills_reviewed', 'qualifications_met', 'applicant_info_reviewed',
            'result', 'notes', 'rejection_reason',
        ])->mapWithKeys(fn ($field) => [$field => $data[$field] ?? null])->all();
        $screeningData += [
            'applicant_id' => $application->id,
            'job_posting_id' => $jobPostingId,
            'screened_by' => $request->user()->id,
        ];
        if ($screening) {
            $screening->update($screeningData);
        } else {
            $screening = HrmApplicationScreening::create($screeningData);
        }
        $next = $data['result'] === 'passed' ? 'Shortlisted' : 'Rejected';
        if ($ja) {
            if (in_array($ja->status, ['Withdrawn', 'Hired'])) {
                return response()->json(['message' => 'This application is already ' . strtolower($ja->status) . ' and cannot be screened.'], 422);
            }
            $ja->update(['status' => $next]);
            $this->refreshApplicantSummary($application, $next === 'Rejected' ? $data['rejection_reason'] ?? $data['notes'] ?? null : null);
        } else {
            $application->update(['status' => $next]);
            ApplicantJobApplication::where('applicant_id', $application->id)
                ->whereNotIn('status', ['Withdrawn', 'Hired'])
                ->notOnHold()
                ->update(['status' => $next]);
            ApplicantApplicationHoldService::reconcileApplicantHolds((int) $application->id);
        }

        if ($data['result'] === 'rejected') {
            NotifiesApplicant::push(
                $application,
                'screening_rejected',
                'Screening Result: Not Selected',
                'Your application was not selected to move forward. Reason: ' . ($data['rejection_reason'] ?? 'No specific reason provided.'),
                ['job_application_id' => $ja?->id, 'rejection_reason' => $data['rejection_reason']]
            );
        }

        return response()->json(['ok' => true, 'status' => $next]);
    }

    public function reject(Request $request, Applicant $application)
    {
        $data = $request->validate([
            'reason' => 'required|string',
            'feedback' => 'nullable|string',
            'job_application_id' => 'nullable|exists:applicant_job_applications,id',
        ]);
        $ja = $this->targetedJobApplication($request, $application);
        if ($ja) {
            // Reject ONLY this job application; the applicant's other
            // applications keep their own statuses.
            if ($ja->is_on_hold) {
                return response()->json(['message' => ApplicantApplicationHoldService::HOLD_MESSAGE], 422);
            }
            if ($ja->status === 'Hired') {
                return response()->json(['message' => 'This application already resulted in a hire and cannot be rejected.'], 422);
            }
            if ($ja->status === 'Withdrawn') {
                return response()->json(['message' => 'This application was withdrawn by the applicant and cannot be rejected.'], 422);
            }
            $ja->update(['status' => 'Rejected']);
            $this->refreshApplicantSummary($application, $data['reason']);
        } else {
            $application->update(['status' => 'Rejected', 'rejection_reason' => $data['reason']]);
            ApplicantJobApplication::where('applicant_id', $application->id)
                ->whereNotIn('status', ['Withdrawn', 'Hired'])
                ->notOnHold()
                ->update(['status' => 'Rejected']);
            ApplicantApplicationHoldService::reconcileApplicantHolds((int) $application->id);
        }

        return response()->json(['ok' => true, 'status' => 'Rejected']);
    }

    /**
     * Resolve the single job application HR acted on (from the card),
     * ensuring it belongs to this applicant. Null = legacy applicant-wide action.
     */
    private function targetedJobApplication(Request $request, Applicant $application): ?ApplicantJobApplication
    {
        $jaId = $request->input('job_application_id') ?? $request->get('job_application_id');
        if (! $jaId) {
            return null;
        }

        return ApplicantJobApplication::where('applicant_id', $application->id)->find($jaId);
    }

    /**
     * Recompute the applicant-level summary status after a single job
     * application changed: while other applications are still active the
     * summary follows the latest active one, so sibling applications are
     * never dragged into Rejected; when nothing active remains the summary
     * mirrors the just-set status.
     */
    private function refreshApplicantSummary(Applicant $application, ?string $rejectionReason = null): void
    {
        $active = ApplicantJobApplication::where('applicant_id', $application->id)
            ->whereNotIn('status', ['Rejected', 'Withdrawn'])
            ->notOnHold()
            ->latest()->first();
        if ($active) {
            $application->update(['status' => $active->status]);
        } else {
            $latest = ApplicantJobApplication::where('applicant_id', $application->id)
                ->notOnHold()
                ->latest()->first();
            $update = ['status' => $latest?->status ?? $application->status];
            if (($update['status'] ?? null) === 'Rejected' && $rejectionReason) {
                $update['rejection_reason'] = $rejectionReason;
            }
            $application->update($update);
        }
    }

    /**
     * Move a Screening application to Shortlisted (ported from super_monti,
     * adapted: acts on the single targeted job application only).
     */
    public function shortlist(Request $request, Applicant $application)
    {
        $ja = $this->targetedJobApplication($request, $application);
        if ($ja) {
            if ($ja->is_on_hold) {
                return response()->json(['message' => ApplicantApplicationHoldService::HOLD_MESSAGE], 422);
            }
            if (! in_array($ja->status, ['Screening'])) {
                return response()->json(['message' => 'Only applications in Screening can be shortlisted.'], 422);
            }
            $ja->update(['status' => 'Shortlisted']);
            $this->refreshApplicantSummary($application);
        } else {
            if ($application->status !== 'Screening') {
                return response()->json(['message' => 'Only applications in Screening can be shortlisted.'], 422);
            }
            $application->update(['status' => 'Shortlisted']);
            ApplicantJobApplication::where('applicant_id', $application->id)
                ->where('status', 'Screening')
                ->notOnHold()
                ->update(['status' => 'Shortlisted']);
        }

        return response()->json(['ok' => true, 'status' => 'Shortlisted']);
    }

    /**
     * Application statistics (ported from super_monti, adapted to
     * per-job-application rows).
     */
    public function stats()
    {
        $base = ApplicantApplicationHoldService::withoutHolds(ApplicantJobApplication::query());

        return response()->json([
            'total' => (clone $base)->count(),
            'on_hold' => ApplicantApplicationHoldService::columnsReady()
                ? ApplicantJobApplication::where('is_on_hold', true)->count()
                : 0,
            'submitted' => (clone $base)->where('status', 'Submitted')->count(),
            'screening' => (clone $base)->where('status', 'Screening')->count(),
            'shortlisted' => (clone $base)->where('status', 'Shortlisted')->count(),
            'interview' => (clone $base)->whereIn('status', ['Interview Scheduled', 'Initial Interview', 'Final Interview', 'Interviewed'])->count(),
            'selected' => (clone $base)->where('status', 'Selected')->count(),
            'onboarding' => (clone $base)->whereIn('status', ['Onboard', 'Onboarding'])->count(),
            'hired' => (clone $base)->where('status', 'Hired')->count(),
            'rejected' => (clone $base)->where('status', 'Rejected')->count(),
            'withdrawn' => (clone $base)->where('status', 'Withdrawn')->count(),
            'by_position' => (clone $base)->select('job_posting_id', DB::raw('count(*) as count'))
                ->groupBy('job_posting_id')->with('jobPosting:id,title')->get()
                ->map(fn ($item) => [
                    'posting_title' => $item->jobPosting?->title ?? 'Unknown',
                    'count' => $item->count,
                ]),
            'by_status' => (clone $base)->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')->get(),
        ]);
    }

    /**
     * Manual HR entry of an application (ported from super_monti, adapted:
     * creates an Applicant row directly since this schema has no separate
     * application table for manual entries).
     */
    public function store(Request $request)
    {
        $allowed = ['Submitted', 'Screening', 'Shortlisted', 'Interview Scheduled', 'Initial Interview', 'Final Interview', 'Interviewed', 'Selected', 'Onboard', 'Onboarding', 'Hired', 'Rejected', 'Withdrawn'];
        $data = $request->validate([
            'job_posting_id' => 'nullable|exists:hrm_job_postings,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:40',
            'cover_letter' => 'nullable|string|max:2000',
            'expected_salary' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'status' => 'nullable|in:' . implode(',', $allowed),
        ]);

        $posting = $data['job_posting_id'] ?? null
            ? HrmJobPosting::find($data['job_posting_id'])
            : null;

        $attributes = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone'] ?? null,
            'job_posting_id' => $posting?->id,
            'position_applied' => $posting?->title,
            'department_id' => $posting?->position?->department_id,
            'interview_feedback' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'Submitted',
        ];

        // expected_salary was dropped by an old migration on some DBs;
        // only write it when the column exists (re-added by
        // 2026_10_03_000001 migration).
        if (Schema::hasColumn('applicants', 'expected_salary')) {
            $attributes['expected_salary'] = $data['expected_salary'] ?? null;
        }

        $applicant = Applicant::create($attributes);

        if ($posting) {
            $applicant->jobApplications()->create([
                'job_posting_id' => $posting->id,
                'status' => $applicant->status,
                'cover_letter' => $data['cover_letter'] ?? null,
            ]);
        }

        return back()->with('success', 'Application created successfully.');
    }

    /**
     * Update applicant fields. A status change here applies applicant-wide
     * (applicant + all non-terminal job applications); per-card changes go
     * through the screening/shortlist/reject actions instead.
     */
    public function update(Request $request, Applicant $application)
    {
        $allowed = ['Submitted', 'Screening', 'Shortlisted', 'Interview Scheduled', 'Initial Interview', 'Final Interview', 'Interviewed', 'Selected', 'Onboard', 'Onboarding', 'Hired', 'Rejected', 'Withdrawn'];
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:40',
            'job_posting_id' => 'nullable|exists:hrm_job_postings,id',
            'notes' => 'nullable|string|max:2000',
            'status' => 'nullable|string|in:' . implode(',', $allowed),
        ]);

        $update = [
            'first_name' => $data['first_name'] ?? $application->first_name,
            'last_name' => $data['last_name'] ?? $application->last_name,
            'email' => $data['email'] ?? $application->email,
            'phone_number' => $data['phone'] ?? $application->phone_number,
            'interview_feedback' => $data['notes'] ?? $application->interview_feedback,
        ];
        if (! empty($data['job_posting_id'])) {
            $posting = HrmJobPosting::find($data['job_posting_id']);
            $update['job_posting_id'] = $posting?->id;
            $update['position_applied'] = $posting?->title ?? $application->position_applied;
        }
        if (! empty($data['status']) && $data['status'] !== $application->status) {
            $update['status'] = $data['status'];
        }
        $application->update($update);
        if (! empty($update['status'])) {
            if (ApplicantApplicationHoldService::isOnboardingStatus($update['status'])) {
                // A single applicant-wide edit must advance only one job
                // application into onboarding; all others retain their true
                // stages and are held by the model's status-change hook.
                $trigger = ApplicantJobApplication::where('applicant_id', $application->id)
                    ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
                    ->notOnHold()
                    ->when($application->job_posting_id, fn ($q) => $q->where('job_posting_id', $application->job_posting_id))
                    ->latest()->first();
                if ($trigger) {
                    $trigger->update(['status' => $update['status']]);
                }
            } else {
                ApplicantJobApplication::where('applicant_id', $application->id)
                    ->whereNotIn('status', ['Withdrawn', 'Hired'])
                    ->notOnHold()
                    ->update(['status' => $update['status']]);
                ApplicantApplicationHoldService::reconcileApplicantHolds((int) $application->id);
            }
        }

        return back()->with('success', 'Application updated successfully.');
    }

    /**
     * Delete an application (job applications cascade). Blocked for hired
     * applicants who already became employees.
     */
    public function destroy(Applicant $application)
    {
        if ($application->status === 'Hired') {
            return back()->with('error', 'Cannot delete a hired applicant record.');
        }

        if (ApplicantApplicationHoldService::columnsReady()
            && ApplicantJobApplication::where('applicant_id', $application->id)->where('is_on_hold', true)->exists()) {
            return back()->with('error', 'Cannot delete an applicant record while a job application is on hold.');
        }

        $applicantId = (int) $application->id;
        $application->delete();

        // Never leave sibling applications stranded when their trigger is deleted.
        ApplicantApplicationHoldService::reconcileApplicantHolds($applicantId);

        return back()->with('success', 'Application deleted successfully.');
    }

    /**
     * Bulk status update for job-application cards (ported from super_monti,
     * adapted: ids are applicant_job_applications ids; terminal Withdrawn /
     * Hired rows are skipped).
     */
    public function bulkUpdate(Request $request)
    {
        $allowed = ['Submitted', 'Screening', 'Shortlisted', 'Interview Scheduled', 'Initial Interview', 'Final Interview', 'Interviewed', 'Selected', 'Onboard', 'Onboarding', 'Hired', 'Rejected', 'Withdrawn'];
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:applicant_job_applications,id',
            'status' => 'required|string|in:' . implode(',', $allowed),
        ]);

        $updated = 0;
        $applicantIds = [];
        foreach (ApplicantJobApplication::whereIn('id', $data['ids'])->get() as $ja) {
            $ja->refresh();
            if ($ja->is_on_hold) {
                continue;
            }
            if ($ja->status === $data['status']) {
                continue;
            }
            if (in_array($ja->status, ['Withdrawn', 'Hired'])) {
                continue;
            }
            $ja->update(['status' => $data['status']]);
            $applicantIds[$ja->applicant_id] = true;
            $updated++;
        }
        foreach (array_keys($applicantIds) as $applicantId) {
            ApplicantApplicationHoldService::reconcileApplicantHolds((int) $applicantId);
            if ($applicant = Applicant::find($applicantId)) {
                $this->refreshApplicantSummary($applicant);
            }
        }

        return response()->json(['ok' => true, 'updated' => $updated, 'status' => $data['status']]);
    }

    /**
     * Applicant-portal friendly status label for one job application
     * (ported from super_monti's getApplicationStatus).
     */
    public function applicationStatus(ApplicantJobApplication $jobApplication)
    {
        $jobApplication->loadMissing(['applicant', 'jobPosting']);

        return response()->json([
            'id' => $jobApplication->id,
            'first_name' => $jobApplication->applicant?->first_name,
            'last_name' => $jobApplication->applicant?->last_name,
            'email' => $jobApplication->applicant?->email,
            'status' => self::portalStatusLabel($jobApplication->status),
            'position' => $jobApplication->jobPosting?->title ?? 'N/A',
            'applied_date' => optional($jobApplication->created_at)?->toDateString(),
            'updated_at' => optional($jobApplication->updated_at)?->toDateString(),
        ]);
    }

    public static function portalStatusLabel(?string $status): string
    {
        return [
            'Submitted' => 'Application Submitted',
            'Screening' => 'Under Review',
            'Shortlisted' => 'Shortlisted',
            'Interview' => 'Interview',
            'Interview Scheduled' => 'Interview Scheduled',
            'Initial Interview' => 'Initial Interview',
            'Final Interview' => 'Final Interview',
            'Interviewed' => 'Interview Completed',
            'Selected' => 'Selected',
            'Onboarding' => 'Onboarding',
            'Onboard' => 'Onboard',
            'Hired' => 'Hired',
            'Rejected' => 'Not Selected',
            'Withdrawn' => 'Withdrawn',
        ][$status ?? ''] ?? ($status ?? 'Unknown');
    }

    /**
     * Quick-view summary for one applicant (ported from super_monti's
     * getProfileSummary, adapted to the applicants table).
     */
    public function profileSummary(Applicant $application)
    {
        $application->loadMissing(['jobPosting', 'documents']);

        return response()->json([
            'id' => $application->id,
            'first_name' => $application->first_name,
            'last_name' => $application->last_name,
            'email' => $application->email,
            'phone' => $application->phone_number,
            'status' => $application->status,
            'position' => $application->jobPosting?->title ?? $application->position_applied ?? 'N/A',
            'department' => $application->jobPosting?->department ?? 'N/A',
            'highest_education' => $application->highest_education ?? 'N/A',
            'school' => $application->school ?? $application->college ?? 'N/A',
            'course' => $application->course ?? 'N/A',
            'profile_photo_url' => ($application->profile_photo_path ?: $application->image)
                ? Storage::url($application->profile_photo_path ?: $application->image)
                : null,
            'application_date' => optional($application->created_at)?->toDateString(),
            'notes' => $application->interview_feedback,
        ]);
    }

    public function export()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'email', 'position', 'status']);
            Applicant::with(['jobPosting', 'jobApplications.jobPosting', 'jobApplications.heldByApplication.jobPosting'])->whereHas('jobApplications')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $a) {
                    $name = trim($a->first_name . ' ' . $a->last_name);
                    $jas = $a->jobApplications ?? collect();
                    if ($jas->isEmpty()) {
                        fputcsv($out, [$name, $a->email, $a->jobPosting?->title, $a->status]);
                    } else {
                        foreach ($jas as $ja) {
                            fputcsv($out, [$name, $a->email, $ja->jobPosting?->title, $ja->status]);
                        }
                    }
                }
            });
            fclose($out);
        }, 'applications.csv');
    }
}
