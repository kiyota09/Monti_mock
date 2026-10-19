<?php

namespace Database\Seeders;

use App\Models\Core\User;
use App\Models\Hrm\Agency;
use App\Models\Hrm\AgencyUser;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\ApplicantDocument;
use App\Models\Hrm\ApplicantJobApplication;
use App\Models\Hrm\ApplicantStatusHistory;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\EmployeeShift;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmEmploymentType;
use App\Models\Hrm\HrmInterview;
use App\Models\Hrm\HrmJobPosting;
use App\Models\Hrm\HrmOnboarding;
use App\Models\Hrm\HrmOnboardingNote;
use App\Models\Hrm\HrmOnboardingTemplate;
use App\Models\Hrm\HrmPosition;
use App\Models\Hrm\HrmTraining;
use App\Models\Hrm\HrmTrainingEnrollment;
use App\Models\Hrm\LeaveRequest;
use App\Models\Hrm\ManpowerAssignment;
use App\Models\Hrm\ManpowerRequest;
use App\Models\Hrm\TraineeGrade;
use App\Models\Hrm\Worker;
use App\Support\OnboardingSnapshot;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * HRMAGENCYAPPLICCANT
 * One-shot demo/QA dataset for the HRM + Agency modules:
 *   1. Department manager accounts (employee_id, org links, page access via role)
 *   2. Published job postings (one per department)
 *   3. Complete applicant applications (+ documents + status history)
 *   4. Interview pipeline (5 Initial + 5 Final, scheduled)
 *   5. Onboarding template per department (items + activities)
 *   6. Applicants currently onboarding (snapshotted + partial progress)
 *   7. Employees with 2 months of attendance (absent/late/leave mixed in)
 *   8. Trainees on a 2-3 week program with attendance + evaluations
 *   9. Manpower agencies / workers / requests / assignment + billing sample
 *
 * Idempotent: rows are keyed on unique columns (email/code) so re-running
 * refreshes the seeded data instead of duplicating it.
 */
class HRMAGENCYAPPLICCANT extends Seeder
{
    private \Faker\Generator $faker;

    private int $empSeq = 0;

    private int $year = 0;

    private ?int $fullTimeId = null;

    private ?int $probationaryId = null;

    /** @var array<string,int> role code => hrm_roles.id */
    private array $roleMap = [];

    public function run(): void
    {
        $this->faker = fake();
        $this->year = (int) now()->year;
        $this->empSeq = (int) User::whereNotNull('employee_id')->count();
        $this->roleMap = DB::table('hrm_roles')->pluck('id', 'code')->all();

        $permMap = DB::table('hrm_roles')->pluck('id', 'code')->all();
        if (empty($permMap)) {
            $this->command->warn('hrm_roles is empty — run migrations first; continuing with null role links.');
        }

        $types = HrmEmploymentType::pluck('id', 'name')->all();
        $this->fullTimeId = $types['Full-time'] ?? (HrmEmploymentType::value('id') ?: null);
        $this->probationaryId = $types['Probationary'] ?? $this->fullTimeId;

        $departments = HrmDepartment::whereNull('archived_at')->orderBy('id')->get();
        if ($departments->isEmpty()) {
            $this->command->error('No hrm_departments found — aborting HRMAGENCYAPPLICCANT.');

            return;
        }

        $managers = $this->seedDepartmentManagers($departments);
        $postings = $this->seedJobPostings($departments, $managers);
        $templates = $this->seedOnboardingTemplates($departments, $managers);

        $this->seedCompleteApplications($departments, $postings, $managers);
        $this->seedInterviewApplicants($departments, $postings, $managers);
        $this->seedOnboardingApplicants($departments, $postings, $managers, $templates);

        $trainings = $this->seedTrainings($managers[array_key_first($managers)] ?? null);
        $this->seedEmployeesWithAttendance($departments, $managers);
        $this->seedTrainees($departments, $managers, $trainings);

        $this->seedAgencies();

        $this->command->info('HRMAGENCYAPPLICCANT seeding complete.');
    }

    // ------------------------------------------------------------------
    // 1. Department managers
    // ------------------------------------------------------------------

    /** @return array<int,User> department id => manager */
    private function seedDepartmentManagers($departments): array
    {
        $managers = [];

        foreach ($departments as $dept) {
            $role = $this->roleFor($dept->category);
            $email = strtolower($dept->code).'.manager@montierp.com';
            $first = $this->faker->firstName();
            $last = $this->faker->lastName();

            $user = User::firstOrNew(['email' => $email]);
            $attrs = [
                'name' => "{$first} {$last}",
                'first_name' => $first,
                'last_name' => $last,
                'password' => 'password',
                'must_change_password' => false,
                'email_verified_at' => now(),
                'role' => $role,
                'position' => 'manager',
                'is_active' => true,
                'department' => $dept->name,
                'hrm_role_id' => $this->roleMap[$role] ?? null,
                'hrm_department_id' => $dept->id,
                'hrm_position_id' => $this->managerPosition($dept)?->id,
                'hrm_employment_type_id' => $this->fullTimeId,
                'join_date' => now()->subMonths(6)->toDateString(),
                'basic_salary' => 45000,
                'work_schedule' => 'Monday - Saturday, 8AM - 5PM',
                'work_location' => 'Main Manufacturing Plant',
                'company' => 'MontiTextile',
                'created_at' => now()->subMonths(6),
                'updated_at' => now(),
            ];
            if (! $user->exists || ! $user->employee_id) {
                $attrs['employee_id'] = $this->nextEmpId();
            }
            $user->forceFill($attrs)->save();

            HrmDepartment::where('id', $dept->id)->update(['head_id' => $user->id]);
            $managers[$dept->id] = $user;
        }

        return $managers;
    }

    // ------------------------------------------------------------------
    // 2. Job postings (one per department)
    // ------------------------------------------------------------------

    /** @return array<int,HrmJobPosting> department id => posting */
    private function seedJobPostings($departments, array $managers): array
    {
        $postings = [];
        $seq = 1;

        foreach ($departments as $dept) {
            $staffPos = $this->staffPosition($dept);
            $manager = $managers[$dept->id] ?? null;
            $title = $staffPos?->name ?? "{$dept->name} Staff";

            $postings[$dept->id] = HrmJobPosting::updateOrCreate(
                ['posting_id' => 'JOB-'.$this->year.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT)],
                [
                    'title' => $title,
                    'position_id' => $staffPos?->id,
                    'department' => $dept->name,
                    'reports_to' => $manager?->name,
                    'employment_type_id' => $this->fullTimeId,
                    'vacancies' => 3,
                    'filled_vacancies' => 0,
                    'salary_min' => 18000,
                    'salary_max' => 28000,
                    'salary_visibility' => 'Show Range',
                    'hiring_priority' => 'Normal',
                    'status' => 'Published',
                    'recruiter' => $manager?->name,
                    'hiring_manager' => $manager?->name,
                    'work_location' => 'Main Manufacturing Plant',
                    'work_mode' => 'On-site',
                    'work_schedule' => 'Monday - Saturday, 8AM - 5PM',
                    'shift' => 'Morning',
                    'recruitment_notes' => "Open role for the {$dept->name} department.",
                    'require_initial_interview' => true,
                    'require_final_interview' => true,
                    'closing_date' => now()->addMonths(2)->toDateString(),
                    'expected_start_date' => now()->addMonth()->toDateString(),
                    'published_at' => now()->subDays(10),
                ]
            );

            $seq++;
        }

        return $postings;
    }

    // ------------------------------------------------------------------
    // 3. Complete applications
    // ------------------------------------------------------------------

    private function seedCompleteApplications($departments, array $postings, array $managers): void
    {
        $statuses = ['Submitted', 'Under Review'];
        $i = 0;

        foreach ($departments as $dept) {
            $posting = $postings[$dept->id] ?? null;
            if (! $posting) {
                continue;
            }
            $status = $statuses[$i % count($statuses)];
            $email = strtolower($dept->code).'.applicant'.($i + 1).'@example.com';
            $first = $this->faker->firstName();
            $last = $this->faker->lastName();

            $applicant = $this->upsertApplicant(
                $email,
                $this->applicantData($email, $first, $last, $posting, $dept, $managers[$dept->id] ?? null, $status, $i)
            );

            $this->resetApplicantChildren($applicant);

            ApplicantJobApplication::create([
                'applicant_id' => $applicant->id,
                'job_posting_id' => $posting->id,
                'status' => $status,
                'cover_letter' => "I am applying for the {$posting->title} role in your {$dept->name} department.",
                'notice_period' => $applicant->notice_period,
                'availability_date' => now()->addDays(30)->toDateString(),
                'resume_path' => 'applicants/'.$applicant->id.'/resume.pdf',
            ]);

            $this->seedApplicantDocuments($applicant, $managers[$dept->id] ?? null);
            ApplicantStatusHistory::create([
                'applicant_id' => $applicant->id,
                'from_status' => null,
                'to_status' => $status,
                'changed_by' => $managers[$dept->id]->id ?? null,
                'reason' => 'Application received and filed.',
            ]);

            $i++;
        }
    }

    // ------------------------------------------------------------------
    // 4. Interview pipeline (5 Initial + 5 Final)
    // ------------------------------------------------------------------

    private function seedInterviewApplicants($departments, array $postings, array $managers): void
    {
        $deptList = array_values($departments->all());
        $total = 10;

        for ($i = 0; $i < $total; $i++) {
            $dept = $deptList[$i % count($deptList)];
            $posting = $postings[$dept->id] ?? null;
            if (! $posting) {
                continue;
            }
            $type = $i < 5 ? 'Initial' : 'Final';
            $email = 'interview.'.strtolower($type).($i + 1).'@example.com';
            $first = $this->faker->firstName();
            $last = $this->faker->lastName();
            $manager = $managers[$dept->id] ?? null;

            $applicant = $this->upsertApplicant(
                $email,
                $this->applicantData($email, $first, $last, $posting, $dept, $manager, 'Interview', 100 + $i)
            );

            $this->resetApplicantChildren($applicant);

            ApplicantJobApplication::create([
                'applicant_id' => $applicant->id,
                'job_posting_id' => $posting->id,
                'status' => 'Interview',
                'cover_letter' => 'Scheduled for the '.$type.' interview.',
                'notice_period' => $applicant->notice_period,
                'availability_date' => now()->addDays(20)->toDateString(),
            ]);

            $this->seedApplicantDocuments($applicant, $manager);

            $date = now()->addDays(3 + $i);
            HrmInterview::create([
                'applicant_id' => $applicant->id,
                'job_posting_id' => $posting->id,
                'candidate_name' => $applicant->name,
                'position' => $posting->title,
                'type' => $type,
                'interviewer_id' => $manager?->id,
                'date' => $date->toDateString(),
                'start_time' => '09:00 AM',
                'end_time' => '10:00 AM',
                'method' => $i % 2 === 0 ? 'Onsite' : 'Online',
                'location' => $i % 2 === 0 ? 'HR Conference Room' : null,
                'meeting_link' => $i % 2 === 0 ? null : 'https://meet.example.com/'.strtolower($type).$i,
                'notes' => "{$type} interview for {$posting->title}.",
                'status' => 'Scheduled',
            ]);

            ApplicantStatusHistory::create([
                'applicant_id' => $applicant->id,
                'from_status' => 'Shortlisted',
                'to_status' => 'Interview',
                'changed_by' => $manager?->id,
                'reason' => "Passed screening - scheduled for {$type} interview.",
            ]);
        }
    }

    // ------------------------------------------------------------------
    // 5. Onboarding templates (one per department)
    // ------------------------------------------------------------------

    /** @return array<int,HrmOnboardingTemplate> department id => template */
    private function seedOnboardingTemplates($departments, array $managers): array
    {
        $templates = [];
        $hrmManager = $managers[array_key_first($managers)] ?? User::where('position', 'manager')->first();

        $items = [
            ['Applicant Requirements', 'Updated Resume / CV', 'file', 'Applicant', 'days_after_start', '0'],
            ['Applicant Requirements', 'Valid Government ID', 'file', 'Applicant', 'days_after_start', '2'],
            ['Applicant Requirements', 'SSS Number / E-1 Form', 'file', 'Applicant', 'days_after_start', '3'],
            ['Applicant Requirements', 'PhilHealth MDR', 'file', 'Applicant', 'days_after_start', '3'],
            ['Applicant Requirements', 'Pag-IBIG MDR', 'file', 'Applicant', 'days_after_start', '3'],
            ['Applicant Requirements', 'NBI Clearance', 'file', 'Applicant', 'days_after_start', '5'],
            ['Applicant Requirements', 'TIN / BIR 1902', 'text', 'Applicant', 'days_after_start', '5'],
            ['Applicant Requirements', 'Medical Certificate', 'file', 'Applicant', 'days_after_start', '5'],
            ['Applicant Requirements', 'Bank Account Details', 'text', 'Applicant', 'days_after_start', '5'],
            ['Applicant Requirements', '2x2 ID Picture', 'file', 'Applicant', 'days_after_start', '1'],
            ['Applicant Requirements', 'Diploma / Transcript of Records', 'file', 'Applicant', 'days_after_start', '7'],
            ['Applicant Requirements', 'Signed Employment Contract', 'file', 'Applicant', 'days_after_start', '7'],
            ['HR Processing', 'Employee ID Assignment', 'text', 'HR', 'days_after_start', '1'],
            ['HR Processing', 'Company Email Account', 'text', 'IT', 'days_after_start', '1'],
            ['HR Processing', 'Workstation & Equipment', 'text', 'Facilities', 'days_after_start', '2'],
            ['HR Processing', 'Payroll Enrollment', 'text', 'HR', 'days_after_start', '3'],
            ['HR Processing', '201 File Completion', 'text', 'HR', 'days_after_start', '10'],
        ];

        $activities = [
            ['Orientation', 'Company Orientation', 'On-site', 60, 'days_after_start', '1'],
            ['Orientation', 'Department Orientation', 'On-site', 60, 'days_after_start', '2'],
            ['Training', 'Workplace Safety Training', 'On-site', 120, 'days_after_start', '3'],
            ['Meeting', 'Company Policy Briefing', 'On-site', 60, 'days_after_start', '3'],
            ['Training', 'Systems Access Setup', 'Online', 45, 'days_after_start', '1'],
            ['Training', 'On-the-Job Training', 'On-site', 480, 'days_after_start', '5'],
        ];

        foreach ($departments as $dept) {
            $prefix = strtoupper((string) preg_replace('/^DEP-/i', '', (string) $dept->code));
            $code = 'ONB-'.(preg_replace('/[^A-Z0-9]/', '', $prefix) ?: 'GEN');

            $template = HrmOnboardingTemplate::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $dept->name.' Onboarding Checklist',
                    'description' => "Standard onboarding checklist for the {$dept->name} department.",
                    'department_id' => $dept->id,
                    'position_id' => null,
                    'employment_type_id' => $this->fullTimeId,
                    'work_mode' => 'On-site',
                    'duration_days' => 14,
                    'status' => 'Active',
                    'version' => 1,
                    'is_default' => $dept->category === 'HRM',
                    'created_by' => $hrmManager?->id,
                ]
            );

            $template->items()->delete();
            $template->activities()->delete();

            foreach ($items as $idx => [$category, $name, $inputType, $party, $ruleType, $ruleValue]) {
                $template->items()->create([
                    'category' => $category,
                    'name' => $name,
                    'description' => null,
                    'input_type' => $inputType,
                    'responsible_party' => $party,
                    'is_required' => true,
                    'applicant_visible' => true,
                    'is_active' => true,
                    'due_rule_type' => $ruleType,
                    'due_rule_value' => $ruleValue,
                    'sort_order' => $idx + 1,
                ]);
            }

            foreach ($activities as $idx => [$type, $title, $method, $minutes, $ruleType, $ruleValue]) {
                $template->activities()->create([
                    'type' => $type,
                    'title' => $title,
                    'description' => null,
                    'default_method' => $method,
                    'default_duration_minutes' => $minutes,
                    'is_required' => true,
                    'applicant_visible' => true,
                    'attendance_required' => $type === 'Training',
                    'must_complete_before_onboarding_completion' => false,
                    'is_active' => true,
                    'sort_order' => $idx + 1,
                ]);
            }

            $templates[$dept->id] = $template;
        }

        return $templates;
    }

    // ------------------------------------------------------------------
    // 6. Applicants currently onboarding
    // ------------------------------------------------------------------

    private function seedOnboardingApplicants($departments, array $postings, array $managers, array $templates): void
    {
        $deptList = array_values($departments->all());
        $total = 5;

        for ($i = 0; $i < $total; $i++) {
            $dept = $deptList[$i % count($deptList)];
            $posting = $postings[$dept->id] ?? null;
            $template = $templates[$dept->id] ?? null;
            $manager = $managers[$dept->id] ?? null;
            if (! $posting || ! $template) {
                continue;
            }

            $email = 'onboarding'.($i + 1).'@example.com';
            $first = $this->faker->firstName();
            $last = $this->faker->lastName();

            $applicant = $this->upsertApplicant(
                $email,
                $this->applicantData($email, $first, $last, $posting, $dept, $manager, 'Onboarding', 200 + $i)
            );

            $this->resetApplicantChildren($applicant);

            ApplicantJobApplication::create([
                'applicant_id' => $applicant->id,
                'job_posting_id' => $posting->id,
                'status' => 'Onboarding',
                'cover_letter' => 'Hired - currently onboarding.',
                'notice_period' => $applicant->notice_period,
                'availability_date' => now()->toDateString(),
            ]);
            $this->seedApplicantDocuments($applicant, $manager);

            $fullName = $applicant->name;
            $start = now()->subDays(3 + $i);

            $onboarding = HrmOnboarding::updateOrCreate(
                ['email' => $email],
                [
                    'applicant_id' => $applicant->id,
                    'template_id' => $template->id,
                    'job_posting_id' => $posting->id,
                    'name' => $fullName,
                    'position' => $posting->title,
                    'department' => $dept->name,
                    'employment_type' => 'Full-time',
                    'work_mode' => 'On-site',
                    'work_location' => 'Main Manufacturing Plant',
                    'work_schedule' => 'Monday - Saturday, 8AM - 5PM',
                    'salary_range' => 'PHP 18,000.00 - PHP 25,000.00',
                    'start_date' => $start->toDateString(),
                    'expected_start_date' => $start->toDateString(),
                    'expected_completion_date' => $start->copy()->addDays(14)->toDateString(),
                    'duration_days' => 14,
                    'duration_started_at' => $start,
                    'assigned_manager' => $manager?->name,
                    'status' => 'In Progress',
                    'progress' => 0,
                ]
            );

            $onboarding->items()->delete();
            $onboarding->activities()->delete();
            $onboarding->notes()->delete();

            OnboardingSnapshot::apply($onboarding->fresh(), $template);

            $progressMap = [4, 6, 8, 10, 12];
            $complete = $progressMap[$i % count($progressMap)];
            $onboardingItems = $onboarding->items()->orderBy('id')->get();
            foreach ($onboardingItems->take($complete) as $item) {
                $item->update([
                    'status' => 'Completed',
                    'submitted_at' => now()->subDays(2),
                    'value' => 'Submitted and verified.',
                ]);
            }
            $required = $onboardingItems->count();
            $done = $onboarding->items()->where('status', 'Completed')->count();
            $onboarding->update(['progress' => $required ? (int) round($done / $required * 100) : 0]);

            HrmOnboardingNote::create([
                'onboarding_id' => $onboarding->id,
                'user_id' => $manager?->id,
                'content' => 'Applicant has completed the initial document requirements. Schedule orientation.',
                'is_hr_private' => false,
            ]);

            ApplicantStatusHistory::create([
                'applicant_id' => $applicant->id,
                'from_status' => 'Interview',
                'to_status' => 'Onboarding',
                'changed_by' => $manager?->id,
                'reason' => 'Passed final interview - moved to onboarding.',
            ]);
        }
    }

    // ------------------------------------------------------------------
    // 7. Employees with two months of attendance
    // ------------------------------------------------------------------

    private function seedEmployeesWithAttendance($departments, array $managers): void
    {
        $deptList = array_values($departments->all());

        for ($i = 0; $i < 8; $i++) {
            $dept = $deptList[$i % count($deptList)];
            $role = $this->roleFor($dept->category);
            $manager = $managers[$dept->id] ?? null;
            $email = 'employee'.($i + 1).'@monti.com';
            $first = $this->faker->firstName();
            $last = $this->faker->lastName();
            $start = now()->subMonths(2)->startOfDay();

            $user = User::firstOrNew(['email' => $email]);
            $attrs = [
                'name' => "{$first} {$last}",
                'first_name' => $first,
                'last_name' => $last,
                'password' => 'password',
                'must_change_password' => false,
                'email_verified_at' => now(),
                'role' => $role,
                'position' => 'staff',
                'is_active' => true,
                'department' => $dept->name,
                'hrm_role_id' => $this->roleMap[$role] ?? null,
                'hrm_department_id' => $dept->id,
                'hrm_position_id' => $this->staffPosition($dept)?->id,
                'hrm_employment_type_id' => $this->fullTimeId,
                'manager_id' => $manager?->id,
                'immediate_supervisor' => $manager?->name,
                'join_date' => $start->toDateString(),
                'basic_salary' => 20000 + $i * 1500,
                'work_schedule' => 'Monday - Saturday, 8AM - 5PM',
                'work_location' => 'Main Manufacturing Plant',
                'company' => 'MontiTextile',
                'created_at' => $start,
                'updated_at' => now(),
            ];
            if (! $user->exists || ! $user->employee_id) {
                $attrs['employee_id'] = $this->nextEmpId();
            }
            $user->forceFill($attrs)->save();

            AttendanceLog::where('user_id', $user->id)->delete();
            LeaveRequest::where('user_id', $user->id)->delete();
            EmployeeShift::where('user_id', $user->id)->forceDelete();

            $this->seedAttendanceFor($user, $start->copy(), now()->copy(), $i, true);
        }
    }

    // ------------------------------------------------------------------
    // 8. Trainees (2-3 week program)
    // ------------------------------------------------------------------

    /** @return array<int,HrmTraining> */
    private function seedTrainings(?User $manager): array
    {
        $definitions = [
            ['Machine Operation Fundamentals', 'Safety and operation of textile machines.', 16, 365],
            ['Workplace Safety & Compliance', 'Occupational safety standards and plant rules.', 8, 365],
            ['Quality Control Basics', 'Inspection and quality assurance procedures.', 12, 180],
            ['Company Systems Orientation', 'Overview of internal tools and processes.', 4, null],
        ];

        $trainings = [];
        foreach ($definitions as [$name, $desc, $hours, $expiry]) {
            $trainings[] = HrmTraining::updateOrCreate(
                ['name' => $name],
                [
                    'description' => $desc,
                    'status' => 'In Progress',
                    'duration_hours' => $hours,
                    'expiry_days' => $expiry,
                    'created_by' => $manager?->id,
                ]
            );
        }

        return $trainings;
    }

    private function seedTrainees($departments, array $managers, array $trainings): void
    {
        $deptList = array_values($departments->all());

        for ($i = 0; $i < 4; $i++) {
            $dept = $deptList[($i + 2) % count($deptList)];
            $role = $this->roleFor($dept->category);
            $manager = $managers[$dept->id] ?? null;
            $email = 'trainee'.($i + 1).'@monti.com';
            $first = $this->faker->firstName();
            $last = $this->faker->lastName();

            $weeks = 2 + ($i % 2); // 2 or 3 weeks
            $start = now()->subDays(7 + ($i % 7))->startOfDay();
            $end = $start->copy()->addWeeks($weeks);
            if ($end->lt(now())) {
                $end = now()->addDays(3);
            }

            $user = User::firstOrNew(['email' => $email]);
            $attrs = [
                'name' => "{$first} {$last}",
                'first_name' => $first,
                'last_name' => $last,
                'password' => 'password',
                'must_change_password' => false,
                'email_verified_at' => now(),
                'role' => $role,
                'position' => 'trainee',
                'is_active' => true,
                'department' => $dept->name,
                'hrm_role_id' => $this->roleMap[$role] ?? null,
                'hrm_department_id' => $dept->id,
                'hrm_position_id' => HrmPosition::where('management_level', 'Trainee')->value('id'),
                'hrm_employment_type_id' => $this->probationaryId,
                'manager_id' => $manager?->id,
                'immediate_supervisor' => $manager?->name,
                'join_date' => $start->toDateString(),
                'training_start_date' => $start->toDateString(),
                'training_end_date' => $end->toDateString(),
                'training_duration_weeks' => $weeks,
                'work_schedule' => 'Monday - Saturday, 8AM - 5PM',
                'work_location' => 'Main Manufacturing Plant',
                'company' => 'MontiTextile',
                'created_at' => $start,
                'updated_at' => now(),
            ];
            if (! $user->exists || ! $user->employee_id) {
                $attrs['employee_id'] = $this->nextEmpId();
            }
            $user->forceFill($attrs)->save();

            AttendanceLog::where('user_id', $user->id)->delete();
            EmployeeShift::where('user_id', $user->id)->forceDelete();

            $this->seedAttendanceFor($user, $start->copy(), $end->copy(), 20 + $i, false);

            $total = 70 + $i * 4;
            TraineeGrade::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'skills_performance' => 70 + $i,
                    'behaviour' => 75 + $i,
                    'technicals' => 68 + $i,
                    'safety_awareness' => 80 + $i,
                    'productivity' => 72 + $i,
                    'total_percentage' => $total,
                    'attendance' => 85,
                    'knowledge' => 74 + $i,
                    'practical_skills' => 76 + $i,
                    'work_behavior' => 82,
                    'safety_compliance' => 88,
                    'final_grade' => $total,
                    'evaluation_status' => 'In Progress',
                    'evaluated_at' => now()->subDays(2),
                    'evaluated_by' => $manager?->id,
                    'manager_recommendation' => 'For Regularization',
                    'weekly_recommendation' => 'Continue Training',
                    'remarks' => 'Showing steady improvement during the training period.',
                    'passed_to_hr' => false,
                    'retraining' => false,
                ]
            );

            foreach ($trainings as $index => $training) {
                $progress = min(100, 40 + ($i * 15) + ($index * 10));
                HrmTrainingEnrollment::updateOrCreate(
                    ['training_id' => $training->id, 'user_id' => $user->id],
                    [
                        'status' => $progress >= 100 ? 'Completed' : 'In Progress',
                        'progress' => $progress,
                        'completed_at' => $progress >= 100 ? now()->subDays(2) : null,
                    ]
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // 9. Agencies / workers / requests / billing
    // ------------------------------------------------------------------

    private function seedAgencies(): void
    {
        $definitions = [
            ['AGY-001', 'Northline Manpower Services', 'Full-time'],
            ['AGY-002', 'Prime Workforce Solutions', 'Contract'],
            ['AGY-003', 'Metro Staffing Partners', 'Temporary'],
        ];

        $agencies = [];
        foreach ($definitions as $idx => [$code, $name]) {
            $agency = Agency::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'tin' => '800-'.str_pad((string) (100 + $idx), 3, '0', STR_PAD_LEFT).'-000-000',
                    'address' => 'General Trias, Cavite',
                    'dole_number' => 'DOLE-'.str_pad((string) (1000 + $idx), 5, '0', STR_PAD_LEFT),
                    'dole_expiry' => now()->addYear()->toDateString(),
                    'contract_start' => now()->subYear()->toDateString(),
                    'contract_end' => now()->addYear()->toDateString(),
                    'service_fee_type' => 'percentage',
                    'service_fee_value' => 10 + $idx,
                    'contact_name' => $this->faker->name(),
                    'contact_email' => 'contact'.($idx + 1).'@'.strtolower(str_replace(' ', '', $name)).'.example.com',
                    'contact_phone' => '0917'.str_pad((string) (4000000 + $idx), 7, '0', STR_PAD_LEFT),
                    'status' => 'Active',
                ]
            );

            AgencyUser::updateOrCreate(
                ['email' => $code.'.account@agency.example.com'],
                [
                    'agency_id' => $agency->id,
                    'name' => $name.' Account Manager',
                    'password' => 'password',
                    'is_active' => true,
                    'must_change_password' => false,
                ]
            );

            $agencies[] = $agency;
        }

        $workers = [];
        $workerSeq = 1;
        foreach ($agencies as $aIndex => $agency) {
            for ($w = 0; $w < 3; $w++) {
                $workers[] = Worker::updateOrCreate(
                    ['code' => 'WRK-'.str_pad((string) $workerSeq, 4, '0', STR_PAD_LEFT)],
                    [
                        'agency_id' => $agency->id,
                        'name' => $this->faker->name(),
                        'birth_date' => now()->subYears(28)->subDays($workerSeq)->toDateString(),
                        'gender' => $workerSeq % 2 === 0 ? 'Female' : 'Male',
                        'phone' => '0999'.str_pad((string) (5000000 + $workerSeq), 7, '0', STR_PAD_LEFT),
                        'email' => 'worker'.$workerSeq.'@agency.example.com',
                        'address' => 'General Trias, Cavite',
                        'position_skill' => ['Machine Operator', 'Warehouse Helper', 'Quality Checker'][$workerSeq % 3],
                        'years_experience' => 1 + ($workerSeq % 5),
                        'proposed_department' => 'Manufacturing',
                        'availability' => 'Immediate',
                        'employment_arrangement' => 'Agency Contract',
                        'gov_id_type' => 'SSS',
                        'gov_id_number' => '33-'.str_pad((string) (6000000 + $workerSeq), 7, '0', STR_PAD_LEFT),
                        'daily_rate' => 500 + ($workerSeq % 5) * 25,
                        'clearance_expiry' => now()->addMonths(6)->toDateString(),
                        'emergency_name' => $this->faker->name(),
                        'emergency_relationship' => 'Sibling',
                        'emergency_phone' => '0918'.str_pad((string) (7000000 + $workerSeq), 7, '0', STR_PAD_LEFT),
                        'status' => $w === 2 ? 'Available' : 'Deployed',
                        'consent_given' => true,
                        'consent_at' => now(),
                        'privacy_notice_version' => 'v1',
                    ]
                );
                $workerSeq++;
            }
        }

        $request = ManpowerRequest::updateOrCreate(
            ['code' => 'MPR-'.$this->year.'-0001'],
            [
                'department' => 'Manufacturing',
                'position_skill' => 'Machine Operator',
                'headcount' => 4,
                'start_date' => now()->subMonth()->toDateString(),
                'end_date' => now()->addMonths(2)->toDateString(),
                'shift' => 'Morning',
                'notes' => 'Temporary augmentation for the production line.',
                'agency_id' => $agencies[0]->id,
                'status' => 'Open',
            ]
        );

        $assigned = array_slice($workers, 0, 3);
        foreach ($assigned as $worker) {
            ManpowerAssignment::updateOrCreate(
                ['request_id' => $request->id, 'worker_id' => $worker->id],
                [
                    'assigned_by' => null,
                    'assigned_by_name' => 'HRM Department',
                    'assigned_at' => now()->subWeeks(2),
                    'released_at' => null,
                ]
            );
        }

        // Attendance for deployed workers (last 10 weekdays).
        foreach ($assigned as $worker) {
            $cursor = now()->subDays(14)->startOfDay();
            while ($cursor->lte(now())) {
                if ($cursor->isWeekend()) {
                    $cursor->addDay();

                    continue;
                }
                $status = 'Present';
                $timeIn = '07:55';
                $timeOut = '17:05';
                if ($cursor->day % 7 === 0) {
                    $status = 'Late';
                    $timeIn = '08:35';
                } elseif ($cursor->day % 11 === 0) {
                    $status = 'Absent';
                    $timeIn = null;
                    $timeOut = null;
                }
                DB::table('agency_attendances')->updateOrInsert(
                    ['worker_id' => $worker->id, 'date' => $cursor->toDateString()],
                    [
                        'request_id' => $request->id,
                        'time_in' => $timeIn,
                        'time_out' => $timeOut,
                        'hours' => $timeIn ? 8 : 0,
                        'overtime_hours' => 0,
                        'status' => $status,
                        'approval' => 'Approved',
                        'agency_confirmation' => 'Confirmed',
                        'rate_snapshot' => $worker->daily_rate,
                        'approved_at' => $cursor,
                        'created_at' => $cursor,
                        'updated_at' => now(),
                    ]
                );
                $cursor->addDay();
            }
        }

        $laborCost = (float) collect($assigned)->sum(fn ($w) => (float) $w->daily_rate * 10);
        $serviceFee = $laborCost * 0.10;
        DB::table('billing_statements')->updateOrInsert(
            ['code' => 'BILL-'.$this->year.'-0001'],
            [
                'agency_id' => $agencies[0]->id,
                'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
                'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
                'status' => 'Issued',
                'labor_cost' => $laborCost,
                'service_fee' => $serviceFee,
                'total' => $laborCost + $serviceFee,
                'paid_at' => null,
                'payment_reference' => null,
                'created_at' => now()->subWeeks(2),
                'updated_at' => now(),
            ]
        );
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    private function upsertApplicant(string $email, array $data): Applicant
    {
        $applicant = Applicant::firstOrNew(['email' => $email]);
        $applicant->forceFill($data)->save();

        return $applicant;
    }

    private function resetApplicantChildren(Applicant $applicant): void
    {
        ApplicantJobApplication::where('applicant_id', $applicant->id)->delete();
        ApplicantDocument::where('applicant_id', $applicant->id)->delete();
        ApplicantStatusHistory::where('applicant_id', $applicant->id)->delete();
        HrmInterview::where('applicant_id', $applicant->id)->delete();

        $existing = HrmOnboarding::where('applicant_id', $applicant->id)->get();
        foreach ($existing as $onboarding) {
            $onboarding->items()->delete();
            $onboarding->activities()->delete();
            $onboarding->notes()->delete();
            $onboarding->delete();
        }
    }

    private function seedApplicantDocuments(Applicant $applicant, ?User $uploader): void
    {
        $types = [
            'resume' => 'Resume.pdf',
            'sss' => 'SSS-E1.pdf',
            'philhealth' => 'PhilHealth-MDR.pdf',
            'pagibig' => 'PagIBIG-MDR.pdf',
            'nbi' => 'NBI-Clearance.pdf',
            'certificate' => 'Diploma.pdf',
        ];

        foreach ($types as $type => $name) {
            ApplicantDocument::create([
                'applicant_id' => $applicant->id,
                'type' => $type,
                'file_path' => "applicants/{$applicant->id}/{$type}.pdf",
                'original_name' => $name,
                'verified' => true,
                'uploaded_by' => $uploader?->id,
            ]);
        }
    }

    /**
     * Full applicant profile payload so every applicant row is "complete".
     */
    private function applicantData(
        string $email,
        string $first,
        string $last,
        HrmJobPosting $posting,
        HrmDepartment $dept,
        ?User $hr,
        string $status,
        int $seed
    ): array {
        $age = 22 + ($seed % 12);
        $birthYear = (int) now()->year - $age;

        return [
            'first_name' => $first,
            'middle_name' => $this->faker->lastName(),
            'last_name' => $last,
            'suffix' => null,
            'image' => null,
            'profile_photo_path' => null,
            'date_of_birth' => sprintf('%04d-%02d-%02d', $birthYear, 1 + ($seed % 12), 1 + ($seed % 27)),
            'place_of_birth' => 'General Trias, Cavite',
            'citizenship' => 'Filipino',
            'weight' => 55 + ($seed % 25),
            'height' => 158 + ($seed % 20),
            'civil_status' => ['Single', 'Married'][$seed % 2],
            'sex' => ['Male', 'Female'][$seed % 2],
            'age' => $age,
            'religion' => 'Roman Catholic',
            'contact_number' => '0917'.str_pad((string) (2000000 + $seed), 7, '0', STR_PAD_LEFT),
            'sss_number' => '34-'.str_pad((string) (1000000 + $seed), 7, '0', STR_PAD_LEFT).'-'.($seed % 10),
            'email' => $email,
            'email_verified_at' => now(),
            'password' => 'password',
            'must_change_password' => false,
            'phone_number' => '0917'.str_pad((string) (3000000 + $seed), 7, '0', STR_PAD_LEFT),
            'street_address' => ($seed + 1).' '.$this->faker->streetName(),
            'street_address_line2' => null,
            'barangay' => 'Barangay '.(($seed % 10) + 1),
            'city' => 'General Trias',
            'state_province' => 'Cavite',
            'postal_zip_code' => '4107',
            'position_applied' => $posting->title,
            'expected_salary' => 18000 + ($seed % 8) * 1500,
            'job_posting_id' => $posting->id,
            'department_id' => $dept->id,
            'org_position_id' => $posting->position_id,
            'employment_type_id' => $posting->employment_type_id,
            'notice_period' => ['immediate', '15_days', '30_days', '60_days'][$seed % 4],
            'status' => $status,
            'sss_file' => null,
            'philhealth_number' => '12-'.str_pad((string) (2000000 + $seed), 7, '0', STR_PAD_LEFT).'-'.($seed % 10),
            'philhealth_file' => null,
            'pagibig_number' => '1210-'.str_pad((string) (3000000 + $seed), 8, '0', STR_PAD_LEFT),
            'pagibig_file' => null,
            'spouse_name' => $seed % 2 === 1 ? $this->faker->name('female') : null,
            'spouse_occupation' => $seed % 2 === 1 ? 'Housewife' : null,
            'spouse_address' => $seed % 2 === 1 ? 'General Trias, Cavite' : null,
            'number_of_children' => $seed % 3,
            'children' => $seed % 3 ? [['name' => 'Dependent '.$seed, 'dob' => '2015-05-10']] : null,
            'mother_name' => $this->faker->name('female'),
            'mother_address' => 'General Trias, Cavite',
            'father_name' => $this->faker->name('male'),
            'father_address' => 'General Trias, Cavite',
            'languages' => 'English, Filipino',
            'emergency_name' => $this->faker->name(),
            'emergency_relationship' => 'Sibling',
            'emergency_phone' => '0999'.str_pad((string) (1000000 + $seed), 7, '0', STR_PAD_LEFT),
            'emergency_address' => 'General Trias, Cavite',
            'special_skills' => 'Machine operation, Quality inspection',
            'elementary_school' => 'General Trias Elementary School',
            'elementary_year' => '2010',
            'high_school' => 'General Trias National High School',
            'high_year' => '2014',
            'college' => 'Cavite State University',
            'college_year' => '2018',
            'highest_education' => 'College Graduate',
            'school' => 'Cavite State University',
            'course' => 'BS Industrial Technology',
            'graduation_year' => '2018',
            'vocational' => null,
            'vocational_year' => null,
            'previous_employment_company' => 'Textile Manufacturing Inc.',
            'previous_employment_when' => '2019 - 2023',
            'previous_employment_position' => 'Production Staff',
            'previous_employment_department' => 'Production',
            'has_employment_record' => true,
            'employment_records' => [[
                'company' => 'Textile Manufacturing Inc.',
                'years_of_service' => '4 years',
                'salary' => '18000',
                'position' => 'Production Staff',
                'reason_for_leaving' => 'Career growth',
            ]],
            'machine_operation' => 'Sewing machine, Loom',
            'referred_by' => 'Walk-in',
            'referred_by_address' => null,
            'related_employees' => null,
            'assigned_module' => $dept->category,
            'assigned_hr_id' => $hr?->id,
            'hired_user_id' => null,
            'archived' => false,
            'rejection_reason' => null,
            'interview_feedback' => null,
        ];
    }

    /**
     * Create attendance logs (and one leave request) across a date range.
     * Guarantees at least one Absent + one Late row for realistic reports.
     */
    private function seedAttendanceFor(User $user, Carbon $start, Carbon $end, int $seed, bool $withLeave): void
    {
        $shiftTypes = ['Morning', 'Afternoon', 'Graveyard'];
        $ranges = ['8AM - 5PM', '2PM - 11PM', '10PM - 6AM'];
        $shiftIndex = $seed % 3;

        $absentAt = 3 + ($seed % 5);
        $lateAt = 8 + ($seed % 4);
        $leaveAt = 14 + ($seed % 5);

        $cursor = $start->copy();
        $workIndex = 0;

        while ($cursor->lte($end)) {
            if ($cursor->isWeekend()) {
                $cursor->addDay();

                continue;
            }

            $date = $cursor->toDateString();

            EmployeeShift::updateOrCreate(
                ['user_id' => $user->id, 'effective_date' => $date],
                [
                    'dept_code' => $user->role,
                    'shift_type' => $shiftTypes[$shiftIndex],
                    'schedule_range' => $ranges[$shiftIndex],
                    'status' => 'active',
                ]
            );

            $status = 'On-Time';
            $clockIn = '07:58 AM';
            $clockOut = '05:02 PM';
            $remarks = null;

            if ($workIndex === $absentAt) {
                $status = 'Absent';
                $clockIn = null;
                $clockOut = null;
                $remarks = 'No clock-in / clock-out.';
            } elseif ($workIndex === $lateAt) {
                $status = 'Late';
                $clockIn = '08:47 AM';
                $clockOut = '05:10 PM';
                $remarks = 'Heavy traffic.';
            } elseif ($withLeave && $workIndex === $leaveAt) {
                $status = 'On Leave';
                $clockIn = null;
                $clockOut = null;
                $remarks = 'Approved vacation leave.';
            }

            AttendanceLog::updateOrCreate(
                ['user_id' => $user->id, 'date' => $date],
                [
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'status' => $status,
                    'remarks' => $remarks,
                    'ip_address' => '127.0.0.1',
                ]
            );

            if ($status === 'On Leave') {
                LeaveRequest::updateOrCreate(
                    ['user_id' => $user->id, 'start_date' => $date],
                    [
                        'leave_type' => 'vacation',
                        'end_date' => $date,
                        'reason' => 'Approved vacation leave (seeded).',
                        'status' => 'approved',
                    ]
                );
            }

            $workIndex++;
            $cursor->addDay();
        }
    }

    private function nextEmpId(): string
    {
        do {
            $this->empSeq++;
            $id = 'EMP-'.str_pad((string) $this->empSeq, 4, '0', STR_PAD_LEFT);
        } while (User::where('employee_id', $id)->exists());

        return $id;
    }

    private function managerPosition(HrmDepartment $dept): ?HrmPosition
    {
        return HrmPosition::where('department_id', $dept->id)
            ->where('management_level', 'Managerial')
            ->orderBy('id')
            ->first()
            ?? HrmPosition::where('department_id', $dept->id)->orderBy('id')->first();
    }

    private function staffPosition(HrmDepartment $dept): ?HrmPosition
    {
        return HrmPosition::where('department_id', $dept->id)
            ->where('management_level', 'Individual Contributor')
            ->orderBy('id')
            ->first()
            ?? HrmPosition::where('department_id', $dept->id)->orderBy('id')->first();
    }

    private function roleFor(?string $category): ?string
    {
        return match ($category) {
            'HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'LOG', 'PRO', 'IT' => $category,
            default => 'HRM',
        };
    }
}
