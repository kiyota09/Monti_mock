<?php

namespace Database\Seeders;

use App\Models\Core\User;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\ApplicantDocument;
use App\Models\Hrm\ApplicantJobApplication;
use App\Models\Hrm\ApplicantStatusHistory;
use App\Models\Hrm\HrmApplicationScreening;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmEmploymentType;
use App\Models\Hrm\HrmInterview;
use App\Models\Hrm\HrmInterviewEvaluation;
use App\Models\Hrm\HrmJobPosting;
use App\Models\Hrm\HrmOnboarding;
use App\Models\Hrm\HrmOnboardingNote;
use App\Models\Hrm\HrmOnboardingTemplate;
use App\Models\Hrm\HrmPosition;
use App\Models\Hrm\HrmRole;
use App\Support\ManufacturingRoles;
use App\Support\OnboardingSnapshot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives every seeded manufacturing staff account a complete, linked HR file:
 * application, screening, interviews, onboarding, and employee record.
 * Safe to rerun; only updates accounts assigned a manufacturing role.
 */
class ManufacturingHiringLifecycleSeeder extends Seeder
{
    public function run(): void
    {
        $department = HrmDepartment::where('category', 'MAN')->whereNull('archived_at')->first();
        $employmentType = HrmEmploymentType::where('name', 'Full-time')->first();
        $hrmRole = HrmRole::where('code', 'MAN')->first();

        if (! $department || ! $employmentType || ! $hrmRole) {
            $this->command?->warn('Manufacturing hiring lifecycle skipped: HRM Manufacturing department, role, or Full-time type is missing.');

            return;
        }

        $manager = $department->head ?: User::where('role', 'MAN')->where('position', 'manager')->first();
        $template = HrmOnboardingTemplate::where('department_id', $department->id)
            ->where('status', 'Active')->orderByDesc('is_default')->first()
            ?: HrmOnboardingTemplate::where('status', 'Active')->orderByDesc('is_default')->first();

        $accounts = User::where('role', 'MAN')
            ->whereIn('manufacturing_role', ManufacturingRoles::allRoles())
            ->orderBy('manufacturing_role')->orderBy('id')->get();

        if ($accounts->isEmpty()) {
            $this->command?->warn('Manufacturing hiring lifecycle skipped: no manufacturing staff accounts exist.');

            return;
        }

        $accountsByRole = $accounts->groupBy('manufacturing_role');
        $roleIndex = 0;
        foreach ($accountsByRole as $role => $roleAccounts) {
            $roleIndex++;
            $label = ManufacturingRoles::label($role);
            $position = HrmPosition::updateOrCreate(
                    ['code' => 'POS-MAN-'.substr(strtoupper(str_replace('_', '', $role)), 0, 20)],
                [
                    'name' => $label,
                    'department_id' => $department->id,
                    'reports_to_id' => null,
                    'management_level' => 'Individual Contributor',
                    'organization_level' => 4,
                    'description' => "Manufacturing employee responsible for the {$label} function.",
                    'responsibilities' => "Carry out assigned {$label} duties, follow plant safety procedures, and record completed work.",
                    'qualifications' => 'Textile production experience or equivalent practical training.',
                    'required_skills' => 'Textile production, equipment safety, teamwork, quality awareness',
                    'salary_min' => 18000,
                    'salary_max' => 30000,
                    'overtime_eligible' => true,
                    'approved_headcount' => max(1, $roleAccounts->count()),
                    'status' => 'active',
                ]
            );

            $posting = HrmJobPosting::updateOrCreate(
                ['posting_id' => 'JOB-MAN-'.strtoupper($role)],
                [
                    'title' => $label,
                    'position_id' => $position->id,
                    'department' => $department->name,
                    'reports_to' => $manager?->name,
                    'employment_type_id' => $employmentType->id,
                    'vacancies' => max(1, $roleAccounts->count()),
                    'filled_vacancies' => $roleAccounts->count(),
                    'salary_min' => 18000,
                    'salary_max' => 30000,
                    'salary_visibility' => 'Show Range',
                    'hiring_priority' => 'Normal',
                    'status' => 'Closed',
                    'recruiter' => $manager?->name,
                    'hiring_manager' => $manager?->name,
                    'work_location' => 'MontiTextile Manufacturing Plant, General Trias, Cavite',
                    'work_mode' => 'On-site',
                    'work_schedule' => 'Monday - Saturday, 8:00 AM - 5:00 PM',
                    'recruitment_notes' => 'Seeded demonstration posting. Filled by the linked manufacturing employees.',
                    'require_initial_interview' => true,
                    'require_final_interview' => true,
                    'closing_date' => now()->subDays(120)->toDateString(),
                    'expected_start_date' => now()->subDays(106)->toDateString(),
                    'published_at' => now()->subDays(180),
                ]
            );

            foreach ($roleAccounts->values() as $accountIndex => $user) {
                DB::transaction(function () use ($user, $accountIndex, $roleIndex, $role, $label, $department, $employmentType, $hrmRole, $manager, $template, $position, $posting) {
                    $seed = ($roleIndex * 100) + $accountIndex + 1;
                    $joinedAt = now()->subDays(35 + ($seed % 55))->startOfDay();
                    $submittedAt = $joinedAt->copy()->subDays(28);
                    $first = $user->first_name ?: (explode(' ', trim((string) $user->name))[0] ?: 'Monti');
                    $last = $user->last_name ?: (collect(explode(' ', trim((string) $user->name)))->last() ?: 'Employee');
                    $phone = '09'.str_pad((string) (170000000 + $user->id), 9, '0', STR_PAD_LEFT);
                    $employeeId = $user->employee_id ?: 'MONTI-'.now()->format('Y').'-MAN-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT);
                    $applicantEmail = 'applicant+man-'.$user->id.'@example.com';

                    $user->forceFill([
                        'first_name' => $first,
                        'last_name' => $last,
                        'employee_id' => $employeeId,
                        'position' => 'staff',
                        'department' => $department->name,
                        'hrm_role_id' => $hrmRole->id,
                        'hrm_department_id' => $department->id,
                        'hrm_position_id' => $position->id,
                        'hrm_employment_type_id' => $employmentType->id,
                        'manager_id' => $manager?->id,
                        'immediate_supervisor' => $manager?->name,
                        'join_date' => $joinedAt->toDateString(),
                        'basic_salary' => 19000 + (($seed % 8) * 1250),
                        'work_schedule' => 'Monday - Saturday, 8:00 AM - 5:00 PM',
                        'work_location' => 'MontiTextile Manufacturing Plant, General Trias, Cavite',
                        'company' => 'MontiTextile',
                        'is_active' => true,
                    ])->save();

                    $applicant = Applicant::where('hired_user_id', $user->id)->first()
                        ?: Applicant::where('email', $user->email)->first()
                        ?: Applicant::where('email', $applicantEmail)->first()
                        ?: new Applicant();
                    $applicant->forceFill([
                            'email' => $applicantEmail,
                            'first_name' => $first,
                            'middle_name' => 'Santos',
                            'last_name' => $last,
                            'phone_number' => $phone,
                            'contact_number' => $phone,
                            'street_address' => ($seed + 1).' Textile Avenue',
                            'street_address_line2' => 'Phase '.(($seed % 4) + 1),
                            'barangay' => 'Manggahan',
                            'city' => 'General Trias',
                            'state_province' => 'Cavite',
                            'postal_zip_code' => '4107',
                            'date_of_birth' => now()->subYears(25 + ($seed % 12))->subDays($seed)->toDateString(),
                            'place_of_birth' => 'Cavite, Philippines',
                            'citizenship' => 'Filipino',
                            'sex' => $seed % 2 ? 'Female' : 'Male',
                            'civil_status' => $seed % 3 ? 'Single' : 'Married',
                            'age' => 25 + ($seed % 12),
                            'religion' => 'Roman Catholic',
                            'sss_number' => '34-'.str_pad((string) (1000000 + $user->id), 7, '0', STR_PAD_LEFT).'-'.($user->id % 10),
                            'philhealth_number' => '12-'.str_pad((string) (2000000 + $user->id), 7, '0', STR_PAD_LEFT).'-'.($user->id % 10),
                            'pagibig_number' => '1210-'.str_pad((string) (3000000 + $user->id), 8, '0', STR_PAD_LEFT),
                            'emergency_name' => $last.' Family Contact',
                            'emergency_relationship' => 'Sibling',
                            'emergency_phone' => '09'.str_pad((string) (180000000 + $user->id), 9, '0', STR_PAD_LEFT),
                            'emergency_address' => 'General Trias, Cavite',
                            'languages' => 'Filipino, English',
                            'elementary_school' => 'General Trias Elementary School',
                            'elementary_year' => '2012',
                            'high_school' => 'General Trias National High School',
                            'high_year' => '2016',
                            'vocational' => 'Cavite Technical Training Center',
                            'vocational_year' => '2018',
                            'highest_education' => 'Vocational Graduate',
                            'school' => 'Cavite Technical Training Center',
                            'course' => 'Textile Production Technology',
                            'graduation_year' => '2018',
                            'special_skills' => 'Textile production, equipment safety, quality checks',
                            'machine_operation' => $label.' equipment and production line procedures',
                            'previous_employment_company' => 'Cavite Textile Works',
                            'previous_employment_when' => '2019 - 2024',
                            'previous_employment_position' => 'Production Operator',
                            'previous_employment_department' => 'Textile Production',
                            'has_employment_record' => true,
                            'employment_records' => [[
                                'company' => 'Cavite Textile Works',
                                'years_of_service' => '5 years',
                                'position' => 'Production Operator',
                                'department' => 'Textile Production',
                                'reason_for_leaving' => 'Career advancement',
                            ]],
                            'position_applied' => $label,
                            'expected_salary' => 19000 + (($seed % 8) * 1250),
                            'notice_period' => '30_days',
                            'status' => 'Hired',
                            'job_posting_id' => $posting->id,
                            'department_id' => $department->id,
                            'org_position_id' => $position->id,
                            'employment_type_id' => $employmentType->id,
                            'assigned_module' => 'MAN',
                            'assigned_hr_id' => $manager?->id,
                            'hired_user_id' => $user->id,
                            'email_verified_at' => $user->email_verified_at ?: now(),
                            'password' => $user->password,
                            'must_change_password' => false,
                            'archived' => false,
                            'interview_feedback' => 'Passed initial and final interviews. Recommended for hire.',
                        ])->save();
                    $applicant->forceFill([
                        'created_at' => $submittedAt,
                        'updated_at' => $joinedAt,
                    ])->saveQuietly();

                    ApplicantJobApplication::where('applicant_id', $applicant->id)->delete();
                    $application = ApplicantJobApplication::create([
                        'applicant_id' => $applicant->id,
                        'job_posting_id' => $posting->id,
                        'status' => 'Hired',
                        'cover_letter' => "I am applying for the {$label} position at MontiTextile. My production experience and safety training fit the needs of the Manufacturing Department.",
                        'notice_period' => '30_days',
                        'availability_date' => $joinedAt->toDateString(),
                        'resume_path' => 'applicants/'.$applicant->id.'/resume.pdf',
                    ]);
                    $application->forceFill(['created_at' => $submittedAt, 'updated_at' => $joinedAt])->saveQuietly();

                    foreach ([
                        'resume' => 'Resume.pdf', 'sss' => 'SSS-E1.pdf', 'philhealth' => 'PhilHealth-MDR.pdf',
                        'pagibig' => 'PagIBIG-MDR.pdf', 'nbi' => 'NBI-Clearance.pdf', 'certificate' => 'Textile-Training-Certificate.pdf',
                    ] as $type => $filename) {
                        ApplicantDocument::updateOrCreate(
                            ['applicant_id' => $applicant->id, 'type' => $type],
                            [
                                'file_path' => "applicants/{$applicant->id}/{$type}.pdf",
                                'original_name' => $filename,
                                'verified' => true,
                                'uploaded_by' => $manager?->id,
                            ]
                        );
                    }

                    HrmApplicationScreening::where('applicant_id', $applicant->id)->delete();
                    $screening = HrmApplicationScreening::create([
                        'applicant_id' => $applicant->id,
                        'job_posting_id' => $posting->id,
                        'resume_reviewed' => true,
                        'education_verified' => true,
                        'work_experience_reviewed' => true,
                        'skills_reviewed' => true,
                        'qualifications_met' => true,
                        'applicant_info_reviewed' => true,
                        'result' => 'passed',
                        'notes' => 'Complete application reviewed; education, textile experience, and role qualifications verified.',
                        'screened_by' => $manager?->id,
                    ]);
                    $screeningDate = $joinedAt->copy()->subDays(25);
                    $screening->forceFill(['created_at' => $screeningDate, 'updated_at' => $screeningDate])->saveQuietly();

                    HrmInterview::where('applicant_id', $applicant->id)->delete();
                    foreach (['Initial', 'Final'] as $interviewIndex => $type) {
                        $interviewDate = $joinedAt->copy()->subDays(18 - ($interviewIndex * 3));
                        $interview = HrmInterview::create([
                            'applicant_id' => $applicant->id,
                            'job_posting_id' => $posting->id,
                            'candidate_name' => $applicant->name,
                            'position' => $label,
                            'type' => $type,
                            'interviewer_id' => $manager?->id,
                            'date' => $interviewDate->toDateString(),
                            'start_time' => $interviewIndex ? '10:00 AM' : '09:00 AM',
                            'end_time' => $interviewIndex ? '11:00 AM' : '10:00 AM',
                            'method' => 'Onsite',
                            'location' => 'HR Conference Room, Manufacturing Plant',
                            'notes' => "Completed {$type} interview for {$label}; candidate demonstrated relevant production experience and safety awareness.",
                            'status' => 'Completed',
                        ]);
                        $interview->forceFill(['created_at' => $interviewDate, 'updated_at' => $interviewDate])->saveQuietly();
                        $evaluation = HrmInterviewEvaluation::create([
                            'interview_id' => $interview->id,
                            'communication_skills' => 4,
                            'relevant_skills' => 4,
                            'technical_knowledge' => 4,
                            'problem_solving' => 4,
                            'work_experience' => 5,
                            'adaptability' => 4,
                            'teamwork' => 5,
                            'professionalism' => 5,
                            'strengths' => 'Relevant textile experience, reliable attendance, and a strong safety mindset.',
                            'concerns' => 'Requires role-specific plant orientation.',
                            'interview_notes' => "Recommended to proceed for {$label}.",
                            'result' => 'passed',
                            'recommendation' => 'Hire',
                            'evaluated_by' => $manager?->id,
                        ]);
                        $evaluation->forceFill(['created_at' => $interviewDate, 'updated_at' => $interviewDate])->saveQuietly();
                    }

                    ApplicantStatusHistory::where('applicant_id', $applicant->id)->delete();
                    $history = [
                        [null, 'Submitted', 'Application and complete candidate profile received.', $joinedAt->copy()->subDays(28)],
                        ['Submitted', 'Under Review', 'Application screening started by Manufacturing HR.', $joinedAt->copy()->subDays(26)],
                        ['Under Review', 'Interview', 'Screening passed; candidate advanced to initial and final interviews.', $joinedAt->copy()->subDays(22)],
                        ['Interview', 'Onboarding', 'Final interview passed; offer accepted and onboarding started.', $joinedAt->copy()->subDays(16)],
                        ['Onboarding', 'Hired', 'Onboarding checklist completed; employee hired into the Manufacturing Department.', $joinedAt],
                    ];
                    foreach ($history as [$from, $to, $reason, $changedAt]) {
                        $statusChange = ApplicantStatusHistory::create([
                            'applicant_id' => $applicant->id,
                            'from_status' => $from,
                            'to_status' => $to,
                            'changed_by' => $manager?->id,
                            'reason' => $reason,
                        ]);
                        $statusChange->forceFill(['created_at' => $changedAt, 'updated_at' => $changedAt])->saveQuietly();
                    }

                    $onboardingStart = $joinedAt->copy()->subDays(14);
                    $onboarding = HrmOnboarding::where('applicant_id', $applicant->id)->first()
                        ?: HrmOnboarding::where('email', $user->email)->first()
                        ?: HrmOnboarding::where('email', $applicantEmail)->first()
                        ?: new HrmOnboarding();
                    $onboarding->forceFill([
                            'email' => $applicantEmail,
                            'applicant_id' => $applicant->id,
                            'template_id' => $template?->id,
                            'job_posting_id' => $posting->id,
                            'name' => $applicant->name,
                            'position' => $label,
                            'department' => $department->name,
                            'employment_type' => 'Full-time',
                            'work_mode' => 'On-site',
                            'work_location' => 'MontiTextile Manufacturing Plant, General Trias, Cavite',
                            'work_schedule' => 'Monday - Saturday, 8:00 AM - 5:00 PM',
                            'salary_range' => 'PHP 18,000.00 - PHP 30,000.00',
                            'start_date' => $onboardingStart->toDateString(),
                            'expected_start_date' => $onboardingStart->toDateString(),
                            'expected_completion_date' => $joinedAt->toDateString(),
                            'duration_days' => 14,
                            'duration_started_at' => $onboardingStart,
                            'assigned_manager' => $manager?->name,
                            'status' => 'Completed',
                            'progress' => 100,
                        ])->save();
                    $onboarding->forceFill([
                        'created_at' => $onboardingStart,
                        'updated_at' => $joinedAt,
                    ])->saveQuietly();

                    $onboarding->items()->delete();
                    $onboarding->activities()->delete();
                    $onboarding->notes()->delete();
                    if ($template) {
                        OnboardingSnapshot::apply($onboarding->fresh(), $template);
                    }
                    $onboarding->items()->update([
                        'status' => 'Completed',
                        'value' => 'Submitted and verified during employee onboarding.',
                        'submitted_at' => $joinedAt,
                    ]);
                    $onboarding->activities()->update([
                        'status' => 'Completed',
                        'attendance_status' => 'Attended',
                        'schedule_date' => $joinedAt->toDateString(),
                    ]);
                    if ($manager) {
                        HrmOnboardingNote::create([
                            'onboarding_id' => $onboarding->id,
                            'user_id' => $manager->id,
                            'content' => 'All hiring documents, plant orientation, safety training, and role-specific onboarding requirements are complete.',
                            'is_hr_private' => false,
                        ]);
                    }
                });
            }
        }

        $this->command?->info('Manufacturing employee hiring and onboarding records seeded for '.$accounts->count().' staff accounts.');
    }
}
