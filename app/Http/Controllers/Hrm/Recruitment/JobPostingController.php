<?php

namespace App\Http\Controllers\Hrm\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\ApplicantJobApplication;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmEmploymentType;
use App\Models\Hrm\HrmJobPosting;
use App\Models\Hrm\HrmPosition;
use App\Models\Hrm\HrmShift;
use App\Services\Hrm\ApplicantApplicationHoldService;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class JobPostingController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        $q = HrmJobPosting::with(['position', 'employmentType']);
        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('posting_id', 'like', "%{$s}%"));
        }
        if ($st = $request->get('status')) {
            $q->where('status', $st);
        }
        if ($d = $request->get('department')) {
            $q->where('department', $d);
        }
        // NOTE: query params arrive as strings, and the string 'false'
        // is truthy in PHP — so parse explicitly instead of truthiness.
        if (filter_var($request->get('showArchived'), FILTER_VALIDATE_BOOLEAN)) {
            $q->whereNotNull('archived_at');
        } else {
            $q->whereNull('archived_at');
        }

        $postings = $q->latest()->paginate(12)->withQueryString();
        // Flatten relations for the table (position/type names, live
        // applicant counts, formatted publish date).
        $postings->setCollection($postings->getCollection()->map(function ($p) {
            $positionName = $p->position?->name;
            $typeName = $p->employmentType?->name;
            $total = ApplicantApplicationHoldService::withoutHolds(
                    ApplicantJobApplication::where('job_posting_id', $p->id)
                )->count()
                + Applicant::where('job_posting_id', $p->id)->count();
            $published = optional($p->published_at)?->toDateString();
            // Drop the loaded relation objects so the flattened strings win serialization.
            unset($p->position, $p->employmentType);
            $p->setAttribute('position', $positionName);
            $p->setAttribute('employment_type', $typeName);
            $p->setAttribute('total_applications', $total);
            $p->setAttribute('published_date', $published);

            return $p;
        }));

        return Inertia::render('Dashboard/HRM_NEW/JobPosting', [
            'postingsData' => $postings,
            'filters' => $request->only(['search', 'status', 'department', 'showArchived']),
            'filterOptions' => [
                'departments' => HrmDepartment::whereNull('archived_at')->orderBy('name')->get(['id', 'name']),
                'statuses' => ['Draft', 'Published', 'Closed', 'Archived'],
                'employmentTypes' => HrmEmploymentType::select('id', 'name')->get(),
            ],
            'metrics' => [
                'active' => HrmJobPosting::where('status', 'Published')->count(),
                'drafts' => HrmJobPosting::where('status', 'Draft')->count(),
                'closed' => HrmJobPosting::where('status', 'Closed')->count(),
                'archived' => HrmJobPosting::whereNotNull('archived_at')->count(),
                'vacancies' => (int) HrmJobPosting::sum('vacancies'),
                'total_applicants' => ApplicantApplicationHoldService::withoutHolds(ApplicantJobApplication::query())->count()
                    + Applicant::whereNotNull('job_posting_id')->count(),
                'hired' => Applicant::where('status', 'Hired')->count(),
                'urgent' => HrmJobPosting::where('hiring_priority', 'Urgent')->count(),
            ],
            // Full position records so the posting modal can auto-fill
            // department / reports-to / salary / vacancies straight from
            // the Positions page (same shaping as the Positions directory).
            'positions' => HrmPosition::with(['department', 'reportsTo'])
                ->whereNull('archived_at')->orderBy('name')->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'department_id' => $p->department_id,
                    'department_name' => $p->department?->name,
                    'reports_to_id' => $p->reports_to_id,
                    'reports_to_name' => $p->reportsTo?->name,
                    'management_level' => $p->management_level,
                    'salary_min' => $p->salary_min,
                    'salary_max' => $p->salary_max,
                    'approved_headcount' => $p->approved_headcount,
                    'filled_positions' => User::where('hrm_position_id', $p->id)->count(),
                ])->values()->all(),
            // Hiring Manager picker: manager level or higher only
            // (manager / secretary / special_officer / vice_president),
            // with department ids so the form can restrict the list to the
            // selected position's department (no other-department managers).
            'hiringManagers' => User::with(['hrmOrgPosition:id,name,code,management_level', 'hrmDepartment:id,name'])
                ->where('is_active', '!=', 0)
                ->whereIn('position', ['manager', 'secretary', 'special_officer', 'vice_president'])
                ->select('id', 'name', 'email', 'role', 'position', 'department', 'hrm_position_id', 'hrm_department_id')
                ->orderBy('name')->take(200)->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'job_position' => $u->hrmOrgPosition?->name ?: ucfirst((string) $u->position),
                    'job_position_code' => $u->hrmOrgPosition?->code,
                    'management_level' => $u->hrmOrgPosition?->management_level,
                    'user_level' => $u->position,
                    'department' => $u->hrmDepartment?->name ?: $u->department,
                    'hrm_department_id' => $u->hrm_department_id,
                    'role' => $u->role,
                ])->values()->all(),
            'workLocation' => 'Km 22 Emilio Aguinaldo Hwy, Anabu 1B, Imus, 4103 Cavite',
            'employmentTypes' => HrmEmploymentType::select('id', 'name')->get(),
            // Shift options come from Shift management (active definitions).
            'shifts' => HrmShift::where('is_active', true)->orderBy('code')
                ->get(['id', 'code', 'name', 'shift_type', 'start_time', 'end_time']),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'position_id' => 'required|exists:hrm_positions,id',
            'employment_type_id' => 'nullable|exists:hrm_employment_types,id',
            'vacancies' => 'required|integer|min:1',
            'hiring_priority' => 'nullable|in:Normal,High,Urgent',
            'work_mode' => 'nullable|in:On-site,Remote,Hybrid',
            'work_schedule' => 'nullable|string',
            'shift' => 'nullable|string|max:100',
            'salary_visibility' => 'nullable|string',
            'recruitment_notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'require_initial_interview' => 'nullable|boolean',
            'require_final_interview' => 'nullable|boolean',
            'training_required' => 'nullable|boolean',
            'closing_date' => 'nullable|date',
            'expected_start_date' => 'nullable|date',
            'department' => 'nullable|string',
            'reports_to' => 'nullable|string',
            'recruiter' => 'nullable|string',
            'hiring_manager' => 'nullable|string',
            'work_location' => 'nullable|string',
        ]);
        // Title + salary are derived from the selected position (read-only).
        $position = HrmPosition::findOrFail($data['position_id']);
        // Work mode is fixed to on-site company policy.
        $data['work_mode'] = 'On-site';
        $this->assertVacanciesWithinHeadcount($position->id, (int) $data['vacancies']);
        $data['title'] = $position->name;
        $data['salary_min'] = $position->salary_min;
        $data['salary_max'] = $position->salary_max;
        // Keep department in sync when the position carries one.
        if (empty($data['department']) && $position->department) {
            $data['department'] = $position->department->name;
        }
        if (empty($data['reports_to']) && $position->reportsTo) {
            $data['reports_to'] = $position->reportsTo->name;
        }
        $data['posting_id'] = 'POST-' . strtoupper(Str::random(8));
        $data['status'] = 'Draft';
        HrmJobPosting::create($data);

        return back()->with('success', 'Job posting created.');
    }

    public function update(Request $request, HrmJobPosting $jobPosting)
    {
        $validated = $request->validate([
            'position_id' => 'sometimes|exists:hrm_positions,id',
            'employment_type_id' => 'nullable|exists:hrm_employment_types,id',
            'vacancies' => 'sometimes|integer|min:1',
            'hiring_priority' => 'nullable|in:Normal,High,Urgent',
            'work_mode' => 'nullable|in:On-site,Remote,Hybrid',
            'work_schedule' => 'nullable|string',
            'shift' => 'nullable|string|max:100',
            'salary_visibility' => 'nullable|string',
            'recruitment_notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'require_initial_interview' => 'nullable|boolean',
            'require_final_interview' => 'nullable|boolean',
            'training_required' => 'nullable|boolean',
            'closing_date' => 'nullable|date',
            'expected_start_date' => 'nullable|date',
            'department' => 'nullable|string',
            'reports_to' => 'nullable|string',
            'recruiter' => 'nullable|string',
            'hiring_manager' => 'nullable|string',
            'work_location' => 'nullable|string',
        ]);
        // Work mode is fixed to on-site company policy.
        $validated['work_mode'] = 'On-site';
        // When the position changes, re-derive title + salary (read-only).
        if (! empty($validated['position_id'])) {
            $position = HrmPosition::findOrFail($validated['position_id']);
            $validated['title'] = $position->name;
            $validated['salary_min'] = $position->salary_min;
            $validated['salary_max'] = $position->salary_max;
        }
        if (isset($validated['vacancies'])) {
            $this->assertVacanciesWithinHeadcount(
                (int) ($validated['position_id'] ?? $jobPosting->position_id),
                (int) $validated['vacancies']
            );
        }
        $jobPosting->update($validated);

        return back()->with('success', 'Job posting updated.');
    }

    /**
     * Number of vacancies may never exceed a position's available headcount
     * (approved headcount minus employees already assigned to the position).
     */
    private function assertVacanciesWithinHeadcount(int $positionId, int $vacancies): void
    {
        $position = HrmPosition::find($positionId);
        if (! $position) {
            return;
        }
        $available = (int) ($position->approved_headcount ?? 0)
            - User::where('hrm_position_id', $positionId)->count();
        if ($available > 0 && $vacancies > $available) {
            throw ValidationException::withMessages([
                'vacancies' => "Number of vacancies cannot exceed the available headcount ({$available}) for {$position->name}.",
            ]);
        }
    }

    public function destroy(HrmJobPosting $jobPosting)
    {
        // Blocked while applications exist (ported from super_monti).
        $applicationCount = ApplicantApplicationHoldService::withoutHolds(
                ApplicantJobApplication::where('job_posting_id', $jobPosting->id)
            )->count()
            + Applicant::where('job_posting_id', $jobPosting->id)->count();
        if ($applicationCount > 0) {
            return back()->with('error', 'Cannot delete posting with existing applications.');
        }
        $jobPosting->delete();

        return back()->with('success', 'Job posting deleted.');
    }

    /**
     * Position details for the posting form (ported from super_monti).
     */
    public function getPositionDetails(Request $request)
    {
        $position = HrmPosition::with(['department', 'reportsTo'])
            ->whereNull('archived_at')
            ->where('status', 'active')
            ->find($request->integer('position_id'));
        if (! $position) {
            return response()->json(['error' => 'Position not found.'], 404);
        }
        $filled = User::where('hrm_position_id', $position->id)->count();

        return response()->json([
            'id' => $position->id,
            'name' => $position->name,
            'code' => $position->code,
            'department_id' => $position->department_id,
            'department_name' => $position->department?->name,
            'reports_to_id' => $position->reports_to_id,
            'reports_to_name' => $position->reportsTo?->name,
            'salary_min' => $position->salary_min,
            'salary_max' => $position->salary_max,
            'approved_headcount' => $position->approved_headcount,
            'filled_positions' => $filled,
            'available_vacancies' => max(0, (int) $position->approved_headcount - $filled),
        ]);
    }

    /**
     * Posting statistics (ported from super_monti, adapted to hrm_* schema).
     */
    public function stats()
    {
        return response()->json([
            'total' => HrmJobPosting::count(),
            'active' => HrmJobPosting::whereNull('archived_at')->where('status', 'Published')->count(),
            'drafts' => HrmJobPosting::whereNull('archived_at')->where('status', 'Draft')->count(),
            'closed' => HrmJobPosting::whereNull('archived_at')->whereIn('status', ['Closed', 'Filled'])->count(),
            'archived' => HrmJobPosting::whereNotNull('archived_at')->count(),
            'total_applicants' => ApplicantApplicationHoldService::withoutHolds(ApplicantJobApplication::query())->count()
                + Applicant::whereNotNull('job_posting_id')->count(),
            'total_hired' => Applicant::where('status', 'Hired')->count(),
            'by_status' => HrmJobPosting::select('status', DB::raw('count(*) as count'))->groupBy('status')->get(),
            'by_employment_type' => HrmJobPosting::select('employment_type_id', DB::raw('count(*) as count'))
                ->groupBy('employment_type_id')->with('employmentType:id,name')->get()
                ->map(fn ($row) => ['employment_type' => $row->employmentType?->name ?? 'Unspecified', 'count' => $row->count]),
        ]);
    }

    private function setStatus(HrmJobPosting $p, string $status)
    {
        $p->update(['status' => $status] + ($status === 'Published' ? ['published_at' => now()] : []));

        return back()->with('success', "Posting {$status}.");
    }

    public function publish(HrmJobPosting $jobPosting) { return $this->setStatus($jobPosting, 'Published'); }
    public function close(HrmJobPosting $jobPosting) { return $this->setStatus($jobPosting, 'Closed'); }
    public function reopen(HrmJobPosting $jobPosting) { return $this->setStatus($jobPosting, 'Published'); }

    /**
     * Unpublish back to Draft (ported from super_monti).
     */
    public function unpublish(HrmJobPosting $jobPosting)
    {
        $jobPosting->update(['status' => 'Draft', 'published_at' => null]);

        return back()->with('success', 'Job posting unpublished successfully.');
    }

    public function duplicate(HrmJobPosting $jobPosting)
    {
        $copy = $jobPosting->replicate();
        $copy->posting_id = 'POST-' . strtoupper(Str::random(8));
        $copy->status = 'Draft';
        $copy->published_at = null;
        $copy->filled_vacancies = 0;
        $copy->save();

        return back()->with('success', 'Posting duplicated.');
    }

    public function archive(HrmJobPosting $jobPosting)
    {
        $jobPosting->update(['archived_at' => now(), 'status' => 'Archived']);

        return back()->with('success', 'Posting archived.');
    }

    public function reactivate(HrmJobPosting $jobPosting)
    {
        $jobPosting->update(['archived_at' => null, 'status' => 'Draft']);

        return back()->with('success', 'Posting reactivated.');
    }

    public function export(Request $request)
    {
        $status = $request->get('status');
        $department = $request->get('department');
        $employmentTypeId = $request->get('employment_type_id');

        return response()->streamDownload(function () use ($status, $department, $employmentTypeId) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['posting_id', 'title', 'position', 'department', 'employment_type', 'salary_min', 'salary_max', 'vacancies', 'filled', 'applications', 'hired', 'status', 'recruiter', 'hiring_manager', 'work_location', 'published_date', 'closing_date']);
            $q = HrmJobPosting::with(['position', 'employmentType'])->whereNull('archived_at');
            if ($status) {
                $q->where('status', $status);
            }
            if ($department) {
                $q->where('department', $department);
            }
            if ($employmentTypeId) {
                $q->where('employment_type_id', $employmentTypeId);
            }
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    fputcsv($out, [
                        $p->posting_id, $p->title,
                        $p->position?->name ?? '', $p->department,
                        $p->employmentType?->name ?? '',
                        $p->salary_min, $p->salary_max,
                        $p->vacancies, $p->filled_vacancies,
                        ApplicantApplicationHoldService::withoutHolds(
                            ApplicantJobApplication::where('job_posting_id', $p->id)
                        )->count(),
                        ApplicantApplicationHoldService::withoutHolds(
                            ApplicantJobApplication::where('job_posting_id', $p->id)->where('status', 'Hired')
                        )->count(),
                        $p->status, $p->recruiter, $p->hiring_manager, $p->work_location,
                        optional($p->published_at)?->format('Y-m-d H:i:s'),
                        optional($p->closing_date)?->format('Y-m-d'),
                    ]);
                }
            });
            fclose($out);
        }, 'job-postings.csv');
    }
}
