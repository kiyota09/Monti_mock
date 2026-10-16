<?php

namespace App\Support;

use App\Models\Hrm\Applicant;
use App\Models\Hrm\ApplicantNotification;

/**
 * Push portal notifications to job applicants (official APPLICANTS module).
 * HRM transitions call this so candidates see updates in their portal.
 */
class NotifiesApplicant
{
    public static function push(Applicant $applicant, string $type, ?string $title, ?string $message, array $data = []): ApplicantNotification
    {
        return ApplicantNotification::create([
            'applicant_id' => $applicant->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public static function welcome(Applicant $applicant): ApplicantNotification
    {
        return self::push(
            $applicant,
            'general',
            'Welcome to Monti Textile Careers',
            'Your applicant account was created. Browse jobs and track every hiring stage here.',
            []
        );
    }

    /** Shared profile-completion check (Jobs gating + Profile page). */
    public static function completion(Applicant $applicant): array
    {
        $fields = [
            'first_name' => $applicant->first_name,
            'last_name' => $applicant->last_name,
            'birth_date' => $applicant->date_of_birth,
            'gender' => $applicant->sex,
            'civil_status' => $applicant->civil_status,
            'email' => $applicant->email,
            'phone' => $applicant->phone_number,
            'street' => $applicant->street_address,
            'barangay' => $applicant->barangay,
            'city' => $applicant->city,
            'province' => $applicant->state_province,
            'highest_education' => $applicant->highest_education,
            'school' => $applicant->school ?? $applicant->college,
            'course' => $applicant->course,
        ];
        $missing = array_keys(array_filter($fields, fn ($v) => empty($v)));
        $total = count($fields);
        $done = $total - count($missing);

        return [
            'percentage' => (int) round($done / max(1, $total) * 100),
            'is_complete' => empty($missing),
            'missing_fields' => $missing,
        ];
    }
}
