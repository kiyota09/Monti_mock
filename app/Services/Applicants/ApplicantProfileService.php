<?php

namespace App\Services\Applicants;

use App\Models\Hrm\Applicant;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the rich profile payload consumed by
 * HRM_NEW/Applications.vue (view + screening modals):
 * application / personal / address / education / employment /
 * emergency / position / job_posting.
 */
class ApplicantProfileService
{
    public function for(Applicant $applicant): array
    {
        $applicant->loadMissing([
            'jobPosting.employmentType', 'jobPosting.position',
            'department', 'orgPosition', 'employmentType',
            'documents', 'statusHistories', 'screenings', 'hrmInterviews.evaluation',
            'interview',
        ]);

        $jp = $applicant->jobPosting;

        return [
            'application' => [
                'id' => $applicant->id,
                'status' => $applicant->status,
                'expected_salary' => $applicant->expected_salary,
                'notice_period' => $applicant->notice_period,
                'notes' => $applicant->interview_feedback,
                'cover_letter' => null,
                'availability_date' => null,
                'application_date' => optional($applicant->created_at)?->toDateString(),
                'updated_at' => optional($applicant->updated_at)?->toDateString(),
                'assigned_module' => $applicant->assigned_module,
                'rejection' => strtolower((string) $applicant->status) === 'rejected' ? [
                    'reason' => $applicant->rejection_reason,
                    'feedback' => null,
                    'rejected_at' => optional($applicant->updated_at)?->toDateString(),
                ] : null,
                'resume_path' => $applicant->documents->firstWhere('type', 'resume')?->file_path,
                'interviews' => $applicant->hrmInterviews->map(fn ($i) => [
                    'id' => $i->id, 'type' => $i->type, 'date' => optional($i->date)?->toDateString(),
                    'start_time' => $i->start_time, 'end_time' => $i->end_time,
                    'method' => $i->method, 'location' => $i->location, 'status' => $i->status,
                ])->values()->all(),
                'history' => $applicant->statusHistories->map(fn ($h) => [
                    'from' => $h->from_status, 'to' => $h->to_status,
                    'reason' => $h->reason, 'at' => optional($h->created_at)?->toDateTimeString(),
                ])->values()->all(),
                'screening' => $applicant->screenings->first(),
                'documents' => $applicant->documents->map(fn ($d) => [
                    'id' => $d->id, 'type' => $d->type,
                    'original_name' => $d->original_name, 'verified' => $d->verified,
                    'url' => Storage::url($d->file_path),
                ])->values()->all(),
            ],
            'personal' => [
                'first_name' => $applicant->first_name,
                'middle_name' => $applicant->middle_name,
                'last_name' => $applicant->last_name,
                'suffix' => null,
                'email' => $applicant->email,
                'phone' => $applicant->phone_number ?: $applicant->contact_number,
                'birth_date' => optional($applicant->date_of_birth)?->toDateString(),
                'place_of_birth' => $applicant->place_of_birth,
                'gender' => $applicant->sex,
                'civil_status' => $applicant->civil_status,
                'citizenship' => $applicant->citizenship,
                'religion' => $applicant->religion,
                'weight' => $applicant->weight,
                'height' => $applicant->height,
                'profile_photo_path' => $applicant->image ? Storage::url($applicant->image) : null,
            ],
            'address' => [
                'street' => trim(($applicant->street_address ?? '') . ' ' . ($applicant->street_address_line2 ?? '')),
                'barangay' => null,
                'city' => $applicant->city,
                'province' => $applicant->state_province,
                'zip_code' => $applicant->postal_zip_code,
            ],
            'education' => [
                'highest_education' => $applicant->college ? 'College' : ($applicant->vocational ? 'Vocational' : ($applicant->high_school ? 'High School' : null)),
                'school' => $applicant->college,
                'course' => null,
                'graduation_year' => $applicant->college_year,
                'elementary_school' => $applicant->elementary_school,
                'elementary_year' => $applicant->elementary_year,
                'high_school' => $applicant->high_school,
                'high_year' => $applicant->high_year,
                'college' => $applicant->college,
                'college_year' => $applicant->college_year,
                'vocational' => $applicant->vocational,
                'vocational_year' => $applicant->vocational_year,
                'special_skills' => $applicant->special_skills,
            ],
            'employment' => [
                'work_experience' => collect($applicant->employment_records ?? [])->map(fn ($e) => [
                    'company' => $e['company'] ?? null,
                    'position' => $e['position'] ?? null,
                    'years' => $e['years'] ?? null,
                    'salary' => $e['salary'] ?? null,
                    'reason' => $e['reason'] ?? null,
                ])->values()->all(),
                'machine_operation' => $applicant->machine_operation,
                'referred_by' => $applicant->referred_by,
            ],
            'emergency' => [
                'name' => $applicant->emergency_name,
                'relationship' => $applicant->emergency_relationship,
                'phone' => $applicant->emergency_phone,
                'address' => $applicant->emergency_address,
            ],
            'position' => [
                'applied' => $applicant->position_applied,
                'org_position' => $applicant->orgPosition?->name,
            ],
            'job_posting' => $jp ? [
                'id' => $jp->id,
                'title' => $jp->title,
                'posting_id' => $jp->posting_id,
                'department' => $jp->department,
                'employment_type' => $jp->employmentType?->name,
            ] : null,
        ];
    }
}
