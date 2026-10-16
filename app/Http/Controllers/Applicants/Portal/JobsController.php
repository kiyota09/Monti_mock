<?php

namespace App\Http\Controllers\Applicants\Portal;

use App\Http\Controllers\Controller;
use App\Models\Hrm\ApplicantJobApplication;
use App\Models\Hrm\ApplicantStatusHistory;
use App\Models\Hrm\HrmEmploymentType;
use App\Models\Hrm\HrmJobPosting;
use App\Support\NotifiesApplicant;
use App\Support\ReapplyCooldown;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Official APPLICANTS jobs board (Dashboard/APPLICANTS/Jobs/index.vue).
 */
class JobsController extends Controller
{
    public function index(Request $request)
    {
        return $this->render($request);
    }

    public function search(Request $request)
    {
        return $this->render($request);
    }

    private function render(Request $request)
    {
        $applicant = $request->user('applicant');

        $q = HrmJobPosting::with(['position', 'employmentType'])
            ->where('status', 'Published')->whereNull('archived_at');

        $params = $request->only(['q', 'department', 'employment_type', 'work_location', 'work_mode']);
        if (! empty($params['q'])) {
            $s = $params['q'];
            $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('department', 'like', "%{$s}%"));
        }
        if (! empty($params['department'])) {
            $q->where('department', $params['department']);
        }
        if (! empty($params['work_location'])) {
            $q->where('work_location', $params['work_location']);
        }
        if (! empty($params['work_mode'])) {
            $q->where('work_mode', $params['work_mode']);
        }
        if (! empty($params['employment_type'])) {
            $q->whereHas('employmentType', fn ($w) => $w->where('name', $params['employment_type']));
        }

        $mine = ApplicantJobApplication::where('applicant_id', $applicant->id)->latest()->get()
            ->groupBy('job_posting_id');

        $postings = $q->latest()->paginate(12)->withQueryString();
        $items = collect($postings->items())->map(function ($p) use ($mine, $applicant) {
            $latest = $mine->get($p->id)?->first();
            // A rejected application frees the job for re-application,
            // same as a withdrawn one — UNLESS the rejection was an
            // Initial/Final interview (or training) failure inside the
            // 6-month cooldown window.
            $isRejected = strtolower((string) $latest?->status) === 'rejected';
            $cooldown = ReapplyCooldown::cooldownFor((int) $applicant->id, (int) $p->id);
            return [
                'id' => $p->id,
                'posting_id' => $p->posting_id,
                'title' => $p->title,
                'department' => $p->department,
                'employment_type' => $p->employmentType?->name,
                'work_location' => $p->work_location,
                'work_mode' => $p->work_mode,
                'shift' => $p->shift,
                'salary_min' => $p->salary_min,
                'salary_max' => $p->salary_max,
                'salary_visibility' => $p->salary_visibility,
                'vacancies' => $p->vacancies,
                'filled_vacancies' => $p->filled_vacancies,
                'closing_date' => optional($p->closing_date)?->toDateString(),
                'published_date' => optional($p->published_at)?->toDateString(),
                'created_at' => optional($p->created_at)->toISOString(),
                'description' => $p->position?->description ?? $p->recruitment_notes,
                'responsibilities' => $p->position?->responsibilities,
                'qualifications' => $p->position?->qualifications,
                'required_skills' => $p->position?->required_skills,
                'has_applied' => $latest && ! $latest->is_withdrawn && ! $isRejected,
                'application_status' => $latest?->status,
                'application_id' => $latest?->id,
                'application_date' => optional($latest?->created_at)?->toDateString(),
                'is_withdrawn' => (bool) $latest?->is_withdrawn,
                'is_rejected' => $isRejected,
                // 6-month same-job cooldown after an interview/training failure.
                'reapply_blocked' => $cooldown['in_cooldown'],
                'can_reapply_at' => $cooldown['can_reapply_at'],
                'failed_at' => $cooldown['failed_at'],
                'failed_stage' => $cooldown['failed_stage'],
            ];
        })->values()->all();

        $base = HrmJobPosting::where('status', 'Published')->whereNull('archived_at');

        return Inertia::render('Dashboard/APPLICANTS/Jobs/index', [
            'jobPostings' => $items,
            'pagination' => [
                'current_page' => $postings->currentPage(),
                'last_page' => $postings->lastPage(),
                'per_page' => $postings->perPage(),
                'total' => $postings->total(),
            ],
            'filters' => [
                'departments' => (clone $base)->distinct()->pluck('department')->filter()->values()->all(),
                'employment_types' => HrmEmploymentType::where('is_active', true)->pluck('name')->all(),
                'work_locations' => (clone $base)->distinct()->pluck('work_location')->filter()->values()->all(),
                'work_modes' => (clone $base)->distinct()->pluck('work_mode')->filter()->values()->all(),
            ],
            'searchParams' => ['q' => '', 'department' => '', 'employment_type' => '', 'work_location' => '', 'work_mode' => ''] + array_filter($params),
            'profileCompletion' => NotifiesApplicant::completion($applicant),
            'applicantResumes' => $applicant->documents()->where('type', 'resume')->pluck('original_name')->filter()->values()->all(),
        ]);
    }

    public function apply(Request $request, HrmJobPosting $job)
    {
        $applicant = $request->user('applicant');

        if ($job->status !== 'Published' || $job->archived_at) {
            return back()->withErrors(['posting' => 'This posting is no longer accepting applications.']);
        }

        // Expired postings (past closing date) cannot accept applications.
        if ($job->closing_date && \Carbon\Carbon::parse($job->closing_date)->startOfDay()->lt(\Carbon\Carbon::today())) {
            return back()->withErrors(['posting' => 'This job posting has expired and is no longer accepting applications.']);
        }

        $completion = NotifiesApplicant::completion($applicant);
        if (! $completion['is_complete']) {
            return back()->withErrors(['profile' => 'Please complete your profile first: ' . implode(', ', $completion['missing_fields'])]);
        }

        $existing = ApplicantJobApplication::where('applicant_id', $applicant->id)
            ->where('job_posting_id', $job->id)->latest()->first();
        // Rejected (like withdrawn) applications don't block re-applying to
        // the same job — a fresh application row is created below — EXCEPT
        // when the rejection was an Initial/Final interview (or training)
        // failure inside the 6-month cooldown window.
        if ($existing && ! $existing->is_withdrawn && strtolower((string) $existing->status) !== 'rejected') {
            return back()->withErrors(['posting' => 'You have already applied for this position.']);
        }
        $cooldown = ReapplyCooldown::cooldownFor((int) $applicant->id, (int) $job->id);
        if ($cooldown['in_cooldown']) {
            return back()->withErrors(['posting' => ReapplyCooldown::blockedMessage($cooldown, $job->title)]);
        }

        $data = $request->validate([
            'notice_period' => 'nullable|string|max:30',
            'availability_date' => 'nullable|date',
            'cover_letter' => 'nullable|string|max:2000',
            'resume_file' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'resume_path' => 'nullable|string|max:500',
        ]);

        // Enforce notice-period rule: availability_date must be >= today + notice days.
        // e.g. 30_Days => today + 30 days; dates before that are rejected.
        if (! empty($data['availability_date'])) {
            $daysMap = [
                'Immediate' => 0,
                '15_Days' => 15,
                '30_Days' => 30,
                '60_Days' => 60,
            ];
            $noticeDays = $daysMap[$data['notice_period'] ?? 'Immediate'] ?? 0;
            $minDate = \Carbon\Carbon::today()->addDays($noticeDays);
            $availability = \Carbon\Carbon::parse($data['availability_date'])->startOfDay();
            if ($availability->lt($minDate)) {
                return back()->withErrors([
                    'availability_date' => 'For ' . ($noticeDays === 0 ? 'Immediate' : $noticeDays . ' days') . ' notice, the earliest available date is ' . $minDate->toDateString() . '.',
                ]);
            }
        }

        $resumePath = $data['resume_path'] ?? null;
        $resumeName = null;
        if ($request->hasFile('resume_file')) {
            $resumePath = $request->file('resume_file')->store('applicants/documents', 'public');
            $resumeName = $request->file('resume_file')->getClientOriginalName();
            $applicant->documents()->create([
                'type' => 'resume', 'file_path' => $resumePath,
                'original_name' => $resumeName, 'uploaded_by' => null,
            ]);
        } elseif ($resumePath) {
            // The picker sends the library file's display name — normalize to
            // the stored file path so this application keeps pointing at the
            // exact resume chosen for THIS job, even if the library grows.
            $picked = $applicant->documents()->where('type', 'resume')->get();
            $match = $picked->firstWhere('file_path', $resumePath)
                ?? $picked->firstWhere('original_name', $resumePath);
            if ($match) {
                $resumePath = $match->file_path;
            }
        }

        $application = ApplicantJobApplication::create([
            'applicant_id' => $applicant->id,
            'job_posting_id' => $job->id,
            'status' => 'Submitted',
            'cover_letter' => $data['cover_letter'] ?? null,
            'notice_period' => $data['notice_period'] ?? null,
            'availability_date' => $data['availability_date'] ?? null,
            'resume_path' => $resumePath,
        ]);

        // Keep the HRM recruitment row linked to the latest posting and
        // reopen the screening cycle for the new application.
        $previousStatus = $applicant->status;
        $applicant->update([
            'job_posting_id' => $job->id,
            'position_applied' => $job->title,
            'department_id' => $job->position?->department_id ?? $applicant->department_id,
            'status' => 'Submitted',
            'archived' => false,
        ]);
        ApplicantStatusHistory::create([
            'applicant_id' => $applicant->id, 'from_status' => $previousStatus,
            'to_status' => 'Submitted', 'changed_by' => null,
            'reason' => 'Applied for ' . $job->title . ' via portal',
        ]);

        return back()->with('success', 'Application submitted for ' . $job->title . '.');
    }
}
