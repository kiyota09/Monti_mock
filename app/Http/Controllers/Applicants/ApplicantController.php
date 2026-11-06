<?php

namespace App\Http\Controllers\Applicants;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\ApplicantJobApplication;
use App\Models\Hrm\ApplicantStatusHistory;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmEmploymentType;
use App\Models\Hrm\HrmInterview;
use App\Models\Hrm\HrmJobPosting;
use App\Models\Hrm\HrmOnboarding;
use App\Models\Hrm\HrmPosition;
use App\Services\Applicants\ApplicantProfileService;
use App\Services\Hrm\ApplicantApplicationHoldService;
use App\Support\NotifiesApplicant;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * APPLICANTS module — applicant master connected to HRM_NEW.
 * Shares the canonical `applicants` table with HRM recruitment;
 * transitions feed interviews (HRM), onboarding (HRM) and the
 * legacy module-assignment flow (Hrm\ApplicantController).
 */
class ApplicantController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        $q = Applicant::with(['jobPosting', 'department', 'orgPosition']);
        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        if ($st = $request->get('status')) {
            $q->where('status', $st);
        }
        if ($request->boolean('archived')) {
            $q->where('archived', true);
        } else {
            $q->where('archived', false);
        }

        $paginated = $q->latest()->paginate(15)->withQueryString();
        $applications = collect($paginated->items())->filter(fn ($a) => $a !== null)->map(function ($a) {
            $arr = $a->toArray();
            $arr['job_posting'] = $a->jobPosting ? ['id' => $a->jobPosting->id, 'title' => $a->jobPosting->title] : null;
            return $arr;
        })->values()->all();

        return Inertia::render('Dashboard/HRM_NEW/Applications', [
            'applications' => $applications,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'metrics' => [
                'total' => Applicant::where('archived', false)->count(),
                'pending' => Applicant::where('status', 'pending')->where('archived', false)->count(),
                'screening' => Applicant::where('status', 'Screening')->count(),
                'interview' => Applicant::where('status', 'Interview')->count(),
            ],
            'filters' => $request->only(['search', 'status', 'position']),
            'filterOptions' => [
                'statuses' => ['Submitted', 'Screening', 'Shortlisted', 'Interview', 'Offered', 'Hired', 'Rejected'],
                'jobPostings' => HrmJobPosting::select('id', 'title')->latest()->take(100)->get(),
                'departments' => HrmDepartment::select('id', 'name')->get(),
                'positions' => HrmPosition::select('id', 'name')->get(),
                'employmentTypes' => HrmEmploymentType::select('id', 'name')->get(),
            ],
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function profile(Applicant $applicant, ApplicantProfileService $profiles)
    {
        return response()->json($profiles->for($applicant));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:applicants,email',
            'phone_number' => 'nullable|string|max:60',
            'position_applied' => 'nullable|string|max:255',
            'job_posting_id' => 'nullable|exists:hrm_job_postings,id',
            'department_id' => 'nullable|exists:hrm_departments,id',
            'org_position_id' => 'nullable|exists:hrm_positions,id',
            'employment_type_id' => 'nullable|exists:hrm_employment_types,id',
            'status' => 'nullable|string|max:40',
        ]);

        $posting = isset($data['job_posting_id']) ? HrmJobPosting::find($data['job_posting_id']) : null;
        $applicant = Applicant::create($data + [
            'status' => $data['status'] ?? 'Submitted',
            'archived' => false,
            'position_applied' => $data['position_applied'] ?? $posting?->title,
            'department_id' => $data['department_id'] ?? $posting?->position?->department_id,
            'assigned_hr_id' => $request->user()->id,
        ]);
        $this->log($applicant, null, $applicant->status, $request->user()->id, 'Filed via Applicants module');

        return back()->with('success', 'Applicant filed.');
    }

    public function update(Request $request, Applicant $applicant)
    {
        $applicant->update($request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:applicants,email,' . $applicant->id,
            'phone_number' => 'nullable|string|max:60',
            'position_applied' => 'nullable|string|max:255',
            'job_posting_id' => 'nullable|exists:hrm_job_postings,id',
            'department_id' => 'nullable|exists:hrm_departments,id',
            'org_position_id' => 'nullable|exists:hrm_positions,id',
            'employment_type_id' => 'nullable|exists:hrm_employment_types,id',
        ]));

        return back()->with('success', 'Applicant updated.');
    }

    /** Accept → route into the HRM interview pipeline (legacy module flow). */
    public function accept(Request $request, Applicant $applicant)
    {
        $data = $request->validate(['module' => 'required|in:HRM,ECO,CRM,SCM,MAN,PROJ,FIN,LOG,IT']);
        $from = $applicant->status;
        $applicant->update(['status' => 'Interview', 'assigned_module' => $data['module'], 'archived' => false]);
        $this->log($applicant, $from, 'Interview', $request->user()->id, 'Accepted → ' . $data['module']);
        ApplicantJobApplication::where('applicant_id', $applicant->id)
            ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
            ->notOnHold()
            ->update(['status' => 'Interview']);
        NotifiesApplicant::push($applicant, 'interview', 'Application Accepted', 'Your application was accepted and routed for interview.', ['position' => $applicant->position_applied, 'status' => 'Interview']);

        return back()->with('message', "Applicant assigned to {$data['module']} for interview.");    }

    /** Schedule a structured HRM_NEW interview for the applicant. */
    public function sendToInterview(Request $request, Applicant $applicant)
    {
        $data = $request->validate([
            'type' => 'nullable|in:Initial,Final',
            'interviewer_id' => 'nullable|exists:users,id',
            'date' => 'nullable|date',
            'start_time' => 'nullable|string|max:10',
            'end_time' => 'nullable|string|max:10',
            'method' => 'nullable|in:Online,Onsite',
            'location' => 'nullable|string|max:255',
        ]);
        $from = $applicant->status;
        $interview = HrmInterview::create($data + [
            'applicant_id' => $applicant->id,
            'job_posting_id' => $applicant->job_posting_id,
            'candidate_name' => trim(($applicant->first_name ?? '') . ' ' . ($applicant->last_name ?? '')),
            'position' => $applicant->position_applied,
            'status' => 'Scheduled',
        ]);
        $applicant->update(['status' => 'Interview', 'archived' => false]);
        $this->log($applicant, $from, 'Interview', $request->user()->id, 'Interview scheduled');
        ApplicantJobApplication::where('applicant_id', $applicant->id)
            ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
            ->notOnHold()
            ->update(['status' => 'Interview']);
        NotifiesApplicant::push($applicant, 'interview', 'Interview Scheduled', 'Your ' . ($interview->type ?? '') . ' interview has been scheduled.', [
            'position' => $applicant->position_applied,
            'interview_type' => $interview->type,
            'date' => optional($interview->date)?->toDateString(),
            'start_time' => $interview->start_time,
            'end_time' => $interview->end_time,
            'location' => $interview->location,
            'meeting_link' => $interview->meeting_link,
            'notes' => $interview->notes,
            'status' => 'Scheduled',
        ]);

        return back()->with('success', 'Interview scheduled.');
    }

    public function reject(Request $request, Applicant $applicant)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $from = $applicant->status;
        $applicant->update(['status' => 'Rejected', 'archived' => true, 'rejection_reason' => $data['reason']]);
        $this->log($applicant, $from, 'Rejected', $request->user()->id, $data['reason']);
        ApplicantJobApplication::where('applicant_id', $applicant->id)
            ->whereNotIn('status', ['Withdrawn', 'Hired'])
            ->notOnHold()
            ->update(['status' => 'Rejected']);
        ApplicantApplicationHoldService::reconcileApplicantHolds((int) $applicant->id);
        NotifiesApplicant::push($applicant, 'rejection', 'Application Update', 'Thank you for your interest. A decision has been made on your application.', ['position' => $applicant->position_applied, 'status' => 'Rejected']);

        return back()->with('message', 'Applicant rejected and archived.');
    }

    public function restore(Applicant $applicant)
    {
        $from = $applicant->status;
        $applicant->update(['archived' => false, 'status' => 'Submitted', 'rejection_reason' => null]);
        $this->log($applicant, $from, 'Submitted', auth()->id(), 'Restored from archive');

        return back()->with('success', 'Applicant restored.');
    }

    /** Convert a hired applicant into onboarding (HRM) + user account. */
    public function hire(Request $request, Applicant $applicant)
    {
        $from = $applicant->status;
        $onboarding = HrmOnboarding::create([
            'applicant_id' => $applicant->id,
            'job_posting_id' => $applicant->job_posting_id,
            'name' => trim(($applicant->first_name ?? '') . ' ' . ($applicant->last_name ?? '')),
            'email' => $applicant->email,
            'position' => $applicant->position_applied,
            'department' => $applicant->department?->name,
            'status' => 'In Progress',
        ]);
        $applicant->update(['status' => 'Hired', 'archived' => false]);
        $this->log($applicant, $from, 'Hired', $request->user()->id, 'Advanced to onboarding #' . $onboarding->id);
        ApplicantJobApplication::where('applicant_id', $applicant->id)
            ->whereNotIn('status', ['Withdrawn', 'Rejected'])
            ->notOnHold()
            ->update(['status' => 'Hired']);
        ApplicantApplicationHoldService::closeAllHeldForApplicant((int) $applicant->id);
        if ($applicant->job_posting_id) {
            HrmJobPosting::whereKey($applicant->job_posting_id)->increment('filled_vacancies');
        }
        NotifiesApplicant::push($applicant, 'onboarding', 'Welcome Aboard!', 'Your application was approved — onboarding is now open in your portal.', ['position' => $applicant->position_applied, 'status' => 'Onboarding']);

        return back()->with('success', 'Applicant hired — onboarding opened.');
    }

    private function log(Applicant $applicant, ?string $from, string $to, $by, ?string $reason): void
    {
        ApplicantStatusHistory::create([
            'applicant_id' => $applicant->id, 'from_status' => $from,
            'to_status' => $to, 'changed_by' => $by, 'reason' => $reason,
        ]);
    }
}
