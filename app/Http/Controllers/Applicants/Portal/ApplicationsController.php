<?php

namespace App\Http\Controllers\Applicants\Portal;

use App\Http\Controllers\Controller;
use App\Models\Hrm\ApplicantJobApplication;
use App\Models\Hrm\HrmInterview;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Official APPLICANTS applications + interviews
 * (Dashboard/APPLICANTS/Application/index.vue, Interviews/index.vue).
 */
class ApplicationsController extends Controller
{
    private const WITHDRAWABLE = ['submitted', 'screening', 'shortlisted', 'interview', 'interview scheduled', 'initial interview', 'final interview', 'interviewed', 'onboard', 'onboarding'];

    public function index(Request $request)
    {
        $applicant = $request->user('applicant');
        $apps = ApplicantJobApplication::with(['jobPosting.employmentType', 'applicant.documents'])
            ->where('applicant_id', $applicant->id)->latest()->get();

        return Inertia::render('Dashboard/APPLICANTS/Application/index', [
            'applications' => $apps->map(fn ($a) => $this->shape($a))->values()->all(),
        ]);
    }

    public function show(Request $request, ApplicantJobApplication $application)
    {
        $applicant = $request->user('applicant');
        abort_unless($application->applicant_id === $applicant->id, 404);
        $application->load('jobPosting.employmentType', 'jobPosting.position', 'applicant.documents');

        $interviews = HrmInterview::where('applicant_id', $applicant->id)
            ->where('job_posting_id', $application->job_posting_id)
            ->latest()->get()->map(fn ($i) => [
                'id' => $i->id, 'type' => $i->type,
                'date' => optional($i->date)?->toDateString(),
                'start_time' => $i->start_time, 'end_time' => $i->end_time,
                'method' => $i->method, 'location' => $i->location,
                'meeting_link' => $i->meeting_link, 'status' => $i->status,
            ])->values()->all();

        return Inertia::render('Dashboard/APPLICANTS/Application/Show', [
            'application' => $this->shape($application),
            'posting' => $application->jobPosting ? [
                'id' => $application->jobPosting->id,
                'title' => $application->jobPosting->title,
                'department' => $application->jobPosting->department,
                'employment_type' => $application->jobPosting->employmentType?->name,
                'work_location' => $application->jobPosting->work_location,
                'work_mode' => $application->jobPosting->work_mode,
                'salary_min' => $application->jobPosting->salary_min,
                'salary_max' => $application->jobPosting->salary_max,
                'description' => $application->jobPosting->position?->description
                    ?? $application->jobPosting->recruitment_notes,
                'responsibilities' => $application->jobPosting->position?->responsibilities,
                'qualifications' => $application->jobPosting->position?->qualifications,
                'required_skills' => $application->jobPosting->position?->required_skills,
            ] : null,
            'interviews' => $interviews,
            'timeline' => $applicant->statusHistories()->take(20)->get()->map(fn ($h) => [
                'from' => $h->from_status, 'to' => $h->to_status,
                'reason' => $h->reason, 'at' => optional($h->created_at)?->toDateString(),
            ])->values()->all(),
        ]);
    }

    public function withdraw(Request $request, ApplicantJobApplication $application)
    {
        $applicant = $request->user('applicant');
        abort_unless($application->applicant_id === $applicant->id, 404);

        if (! in_array(strtolower((string) $application->status), self::WITHDRAWABLE, true)) {
            return back()->withErrors(['status' => 'Only active applications can be withdrawn.']);
        }
        $application->update(['status' => 'Withdrawn']);

        return back()->with('success', 'Application withdrawn. You may re-apply while the posting is open.');
    }

    public function interviews(Request $request)
    {
        $applicant = $request->user('applicant');
        $items = HrmInterview::where('applicant_id', $applicant->id)
            ->latest()->get()->map(fn ($i) => [
                'id' => $i->id,
                'application_id' => ApplicantJobApplication::where('applicant_id', $applicant->id)
                    ->where('job_posting_id', $i->job_posting_id)->value('id'),
                'position' => $i->position ?? $i->jobPosting?->title,
                'type' => $i->type,
                'date' => optional($i->date)?->toDateString(),
                'start_time' => $i->start_time,
                'end_time' => $i->end_time,
                'method' => $i->method,
                'location' => $i->location,
                'meeting_link' => $i->meeting_link,
                'notes' => $i->notes,
                'interviewer' => $i->interviewer?->name,
                'interviewer_id' => $i->interviewer_id,
                'status' => $i->status,
                'created_at' => optional($i->created_at)->toISOString(),
                'updated_at' => optional($i->updated_at)->toISOString(),
            ])->values()->all();

        // The page refreshes via axios expecting a raw array.
        if ($request->wantsJson()) {
            return response()->json($items);
        }

        return Inertia::render('Dashboard/APPLICANTS/Interviews/index', [
            'interviews' => $items,
        ]);
    }

    /**
     * Applicant confirms attendance for a scheduled interview
     * (ported from super_monti, adapted to hrm_interviews).
     */
    public function confirmInterview(Request $request, HrmInterview $interview)
    {
        $applicant = $request->user('applicant');
        abort_unless($interview->applicant_id === $applicant->id, 404);

        if (in_array($interview->status, ['Completed', 'Cancelled'])) {
            return back()->with('error', "This interview has already been {$interview->status}.");
        }

        $interview->update([
            'status' => 'Scheduled',
            'notes' => trim(($interview->notes ?? '') . ' [Applicant confirmed attendance]'),
        ]);

        return back()->with('success', 'Interview confirmed successfully.');
    }

    /**
     * Applicant requests an interview reschedule
     * (ported from super_monti, adapted to hrm_interviews).
     */
    public function rescheduleInterview(Request $request, HrmInterview $interview)
    {
        $applicant = $request->user('applicant');
        abort_unless($interview->applicant_id === $applicant->id, 404);

        if (in_array($interview->status, ['Completed', 'Cancelled'])) {
            return back()->with('error', "This interview has already been {$interview->status} and cannot be rescheduled.");
        }

        $data = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|string',
            'end_time' => 'nullable|string',
            'reason' => 'nullable|string|max:1000',
        ]);

        $interview->update([
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'] ?? $interview->end_time,
            'status' => 'Scheduled',
            'notes' => trim(($interview->notes ?? '') . ' ' . ($data['reason'] ?? '') . ' [Applicant requested reschedule]'),
        ]);

        return back()->with('success', 'Interview rescheduled successfully. Please wait for the HR confirmation.');
    }

    /**
     * Applicant cancels their interview
     * (ported from super_monti, adapted to hrm_interviews).
     */
    public function cancelInterview(Request $request, HrmInterview $interview)
    {
        $applicant = $request->user('applicant');
        abort_unless($interview->applicant_id === $applicant->id, 404);

        if (in_array($interview->status, ['Completed', 'Cancelled'])) {
            return back()->with('error', "This interview has already been {$interview->status}.");
        }

        $data = $request->validate(['reason' => 'nullable|string|max:1000']);

        $interview->update([
            'status' => 'Cancelled',
            'cancel_reason' => $data['reason'] ?? $interview->cancel_reason,
            'notes' => trim(($interview->notes ?? '') . ' ' . ($data['reason'] ?? '') . ' [Applicant cancelled]'),
        ]);

        return back()->with('success', 'Interview cancelled successfully.');
    }

    /**
     * Portal-friendly status label for one job application
     * (ported from super_monti's status map).
     */
    public function applicationStatus(Request $request, ApplicantJobApplication $application)
    {
        $applicant = $request->user('applicant');
        abort_unless($application->applicant_id === $applicant->id, 404);
        $application->loadMissing('jobPosting');

        return response()->json([
            'id' => $application->id,
            'status' => \App\Http\Controllers\Hrm\Recruitment\ApplicationController::portalStatusLabel($application->status),
            'raw_status' => $application->status,
            'position' => $application->jobPosting?->title ?? 'N/A',
            'applied_date' => optional($application->created_at)?->toDateString(),
            'updated_at' => optional($application->updated_at)?->toDateString(),
        ]);
    }

    private function shape(ApplicantJobApplication $a): array
    {
        $p = $a->jobPosting;
        // This job's own resume (not the applicant's first library file).
        $resumeDoc = $a->resolveResumeDocument();
        $resumePath = $resumeDoc?->file_path ?? $a->resume_path;
        $resumeName = $resumeDoc?->original_name
            ?? ($a->resume_path ? basename($a->resume_path) : null);
        $cooldown = \App\Support\ReapplyCooldown::cooldownFor((int) $a->applicant_id, $a->job_posting_id);

        return [
            'id' => $a->id,
            'job_posting_id' => $a->job_posting_id,
            'job_title' => $p?->title ?? 'General Application',
            'department' => $p?->department,
            'employment_type' => $p?->employmentType?->name,
            'work_location' => $p?->work_location,
            'status' => $a->status,
            'application_date' => optional($a->created_at)->toDateString(),
            'created_at' => optional($a->created_at)->toISOString(),
            'updated_at' => optional($a->updated_at)->toISOString(),
            'progress' => $this->progress($a->status),
            'cover_letter' => $a->cover_letter,
            'resume_path' => $resumePath,
            'resume_name' => $resumeName,
            // 6-month same-job cooldown after an interview/training failure.
            'reapply_blocked' => $cooldown['in_cooldown'],
            'can_reapply_at' => $cooldown['can_reapply_at'],
            'failed_at' => $cooldown['failed_at'],
            'failed_stage' => $cooldown['failed_stage'],
        ];
    }

    private function progress(string $status): array
    {
        $stages = ['Submitted', 'Screening', 'Shortlisted', 'Interview', 'Offered', 'Onboarding', 'Hired'];
        $rank = array_search($status, $stages, true);
        if ($rank === false) {
            return in_array($status, ['Withdrawn', 'Rejected'], true) ? [$status] : ['Submitted'];
        }

        return array_slice($stages, 0, $rank + 1);
    }
}
