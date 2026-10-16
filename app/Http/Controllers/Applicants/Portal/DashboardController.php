<?php

namespace App\Http\Controllers\Applicants\Portal;

use App\Http\Controllers\Controller;
use App\Models\Hrm\HrmInterview;
use App\Models\Hrm\HrmJobPosting;
use App\Support\NotifiesApplicant;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Official APPLICANTS portal home (Dashboard/APPLICANTS/Dashboard.vue).
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $applicant = $request->user('applicant');
        $apps = $applicant->jobApplications()->with('jobPosting')->latest()->get();
        $interviews = HrmInterview::where('applicant_id', $applicant->id)
            ->with('jobPosting')->latest()->get();

        $upcoming = $interviews->where('status', 'Scheduled')->take(5)->map(fn ($i) => [
            'id' => $i->id,
            'job_title' => $i->jobPosting?->title ?? $i->position ?? $applicant->position_applied,
            'interview_type' => $i->type,
            'date' => optional($i->date)?->toDateString(),
            'time' => trim(($i->start_time ?? '') . ($i->end_time ? ' - ' . $i->end_time : '')),
            'location' => $i->location ?? $i->method,
        ])->values()->all();

        $appliedIds = $apps->pluck('job_posting_id')->filter()->all();
        $skills = array_filter(array_map('trim', explode(',', (string) ($applicant->special_skills ?? ''))));
        $recommended = HrmJobPosting::with(['position', 'employmentType'])
            ->where('status', 'Published')->whereNull('archived_at')
            ->whereNotIn('id', $appliedIds ?: [0])
            ->latest()->take(6)->get()->map(function ($p) use ($applicant, $skills) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'department' => $p->department,
                    'location' => $p->work_location,
                    'match' => $this->matchScore($p, $applicant, $skills),
                ];
            })->values()->all();

        // Hired banner payload (ported from super_monti).
        $hiredApp = $apps->firstWhere('status', 'Hired');

        return Inertia::render('Dashboard/APPLICANTS/Dashboard', [
            'stats' => [
                'applications_count' => $apps->count(),
                'interviews_count' => $interviews->count(),
                'pending_applications' => $apps->whereIn('status', ['Submitted', 'Screening', 'Shortlisted'])->count(),
            ],
            'recentApplications' => $apps->take(5)->map(fn ($a) => [
                'id' => $a->id,
                'job_title' => $a->jobPosting?->title ?? $applicant->position_applied,
                'status' => $a->status,
                'application_date' => optional($a->created_at)->toDateString(),
            ])->values()->all(),
            'upcomingInterviews' => $upcoming,
            'recommendedJobs' => $recommended,
            'hiredNotice' => $hiredApp ? [
                'job_title' => $hiredApp->jobPosting?->title ?? $applicant->position_applied,
                'date' => optional($hiredApp->updated_at)->toDateString(),
            ] : null,
        ]);
    }

    /**
     * Skill-based recommendation score 35-100 (ported from super_monti,
     * adapted to special_skills CSV + position_applied keywords).
     */
    private function matchScore($posting, $applicant, array $skills): int
    {
        $score = 35;
        $haystack = strtolower(implode(' ', array_filter([
            $posting->title, $posting->department,
            $posting->employmentType?->name,
            $posting->position?->required_skills,
            $posting->recruitment_notes,
        ])));

        $keywords = $skills;
        foreach (preg_split('/[\s,]+/', (string) ($applicant->position_applied ?? '')) as $word) {
            $word = trim($word);
            if (strlen($word ?? '') > 2) {
                $keywords[] = $word;
            }
        }
        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim((string) $keyword));
            if (strlen($keyword) > 2 && str_contains($haystack, $keyword)) {
                $score += 25;
                break;
            }
        }
        $applicantDept = $applicant->department?->name ?? (is_string($applicant->department ?? null) ? $applicant->department : null);
        if ($posting->department && $applicantDept && $posting->department === $applicantDept) {
            $score += 15;
        }
        if ($posting->employmentType?->name && $applicant->position_applied
            && stripos($posting->employmentType->name, $applicant->position_applied) !== false) {
            $score += 10;
        }
        if ($posting->hiring_priority === 'Urgent') {
            $score += 5;
        }

        return min(100, $score);
    }
}
