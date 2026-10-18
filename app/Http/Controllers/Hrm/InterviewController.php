<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\Interview;
use App\Models\Core\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Traits\HasPagePermissions;

class InterviewController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        // HRM now manages ALL interviews across the whole ERP
        // Fetch all applicants with status 'Interview', regardless of assigned_module
        $applicants = Applicant::with('interview')
            ->where('status', 'Interview')
            ->where('archived', false)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($a) => $this->formatApplicant($a));

        // Page permissions are always under HRM module
        $permissions = $this->getPagePermissionsForModule('HRM');

        return Inertia::render('Dashboard/HRM_NEW/Interview', [
            'applicants' => $applicants,
            'currentModule' => 'HRM',   // fixed for HRM
            'permissions' => $permissions,
        ]);
    }

    public function schedule(Request $request, $id)
    {
        $request->validate([
            'scheduled_at' => 'required|date',
            'interview_type' => 'required|string',
            'duration' => 'nullable|integer',
            'interviewer' => 'nullable|string',
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $applicant = Applicant::findOrFail($id);
        Interview::updateOrCreate(
            ['applicant_id' => $applicant->id],
            $request->only('scheduled_at', 'interview_type', 'duration', 'interviewer', 'location', 'notes')
        );

        return back()->with('message', 'Interview scheduled.');
    }

    public function pass(Request $request, $id)
    {
        $applicant = Applicant::findOrFail($id);
        $module = $applicant->assigned_module ?: 'HRM';  // Keep the original module for trainee role

        // Idempotent: re-passing an already-converted applicant reuses the account.
        $user = User::firstOrCreate(['email' => $applicant->email], [
            'name' => $applicant->first_name.' '.$applicant->last_name,
            'password' => Hash::make('password'),
            'must_change_password' => true,
            'role' => $module,
            'position' => 'trainee',
            'is_active' => true,
            'join_date' => now(),
            'employee_id' => $this->generateEmployeeId($module),
        ]);

        $applicant->update([
            'status' => 'Passed',
            'archived' => false,
            'interview_feedback' => $request->feedback ?? null,
        ]);

        return back()->with('message', 'Applicant passed interview and became trainee.');
    }

    public function fail(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string']);
        $applicant = Applicant::findOrFail($id);
        $applicant->update([
            'status' => 'Failed Interview',
            'archived' => true,
            'rejection_reason' => $request->reason,
        ]);

        return back()->with('message', 'Applicant failed interview and archived.');
    }

    public function passToOtherModule(Request $request, $id)
    {
        $request->validate(['module' => 'required|in:HRM,ECO,CRM,SCM,MAN,PROJ,FIN,LOG,IT']);
        $applicant = Applicant::findOrFail($id);
        $applicant->update([
            'assigned_module' => $request->module,
            'status' => 'Interview',
            'archived' => false,
        ]);

        return back()->with('message', "Applicant passed to {$request->module} for interview.");
    }

    private function generateEmployeeId($role)
    {
        $year = now()->year;
        $last = User::where('employee_id', 'like', "MONTI-{$year}-{$role}-%")
            ->orderBy('employee_id', 'desc')
            ->first();
        $num = $last ? (int) substr($last->employee_id, -4) + 1 : 1;
        return sprintf('MONTI-%s-%s-%04d', $year, $role, $num);
    }

    /**
     * Format applicant data with full details for the frontend.
     */
    private function formatApplicant($applicant)
    {
        // Decode JSON columns if any
        $children = $applicant->children ? json_decode($applicant->children, true) : null;
        $employmentRecords = $applicant->employment_records ? json_decode($applicant->employment_records, true) : null;
        $relatedEmployees = $applicant->related_employees ? json_decode($applicant->related_employees, true) : null;

        return [
            // Basic info
            'id' => $applicant->id,
            'name' => $applicant->first_name.' '.$applicant->last_name,
            'first_name' => $applicant->first_name,
            'middle_name' => $applicant->middle_name,
            'last_name' => $applicant->last_name,
            'suffix' => $applicant->suffix,
            'email' => $applicant->email,
            'phone' => $applicant->phone_number,
            'position_applied' => $applicant->position_applied,
            'status' => $applicant->status,
            'assigned_module' => $applicant->assigned_module,
            'created_at' => $applicant->created_at,
            'profile_photo' => $applicant->profile_photo_path
                ? Storage::url($applicant->profile_photo_path)
                : ($applicant->image ? Storage::url($applicant->image) : null),

            // Personal details
            'date_of_birth' => $applicant->date_of_birth,
            'place_of_birth' => $applicant->place_of_birth,
            'citizenship' => $applicant->citizenship,
            'weight' => $applicant->weight,
            'height' => $applicant->height,
            'civil_status' => $applicant->civil_status,
            'sex' => $applicant->sex,
            'age' => $applicant->age,
            'religion' => $applicant->religion,
            'languages' => $applicant->languages,
            'special_skills' => $applicant->special_skills,
            'machine_operation' => $applicant->machine_operation,

            // Contact & Address
            'street_address' => $applicant->street_address,
            'street_address_line2' => $applicant->street_address_line2,
            'barangay' => $applicant->barangay,
            'city' => $applicant->city,
            'state_province' => $applicant->state_province,
            'postal_zip_code' => $applicant->postal_zip_code,

            // Government IDs
            'sss_number' => $applicant->sss_number,
            'sss_file_url' => $applicant->sss_file ? Storage::url($applicant->sss_file) : null,
            'philhealth_number' => $applicant->philhealth_number,
            'philhealth_file_url' => $applicant->philhealth_file ? Storage::url($applicant->philhealth_file) : null,
            'pagibig_number' => $applicant->pagibig_number,
            'pagibig_file_url' => $applicant->pagibig_file ? Storage::url($applicant->pagibig_file) : null,

            // Family
            'spouse_name' => $applicant->spouse_name,
            'spouse_occupation' => $applicant->spouse_occupation,
            'spouse_address' => $applicant->spouse_address,
            'number_of_children' => $applicant->number_of_children,
            'children' => $children,
            'mother_name' => $applicant->mother_name,
            'mother_address' => $applicant->mother_address,
            'father_name' => $applicant->father_name,
            'father_address' => $applicant->father_address,

            // Emergency contact
            'emergency_name' => $applicant->emergency_name,
            'emergency_relationship' => $applicant->emergency_relationship,
            'emergency_phone' => $applicant->emergency_phone,
            'emergency_address' => $applicant->emergency_address,

            // Education
            'highest_education' => $applicant->highest_education,
            'school' => $applicant->school,
            'course' => $applicant->course,
            'graduation_year' => $applicant->graduation_year,
            'elementary_school' => $applicant->elementary_school,
            'elementary_year' => $applicant->elementary_year,
            'high_school' => $applicant->high_school,
            'high_year' => $applicant->high_year,
            'college' => $applicant->college,
            'college_year' => $applicant->college_year,
            'vocational' => $applicant->vocational,
            'vocational_year' => $applicant->vocational_year,

            // Employment History
            'has_employment_record' => (bool) $applicant->has_employment_record,
            'employment_records' => $employmentRecords,
            'previous_employment_company' => $applicant->previous_employment_company,
            'previous_employment_when' => $applicant->previous_employment_when,
            'previous_employment_position' => $applicant->previous_employment_position,
            'previous_employment_department' => $applicant->previous_employment_department,

            // Referral
            'referred_by' => $applicant->referred_by,
            'referred_by_address' => $applicant->referred_by_address,
            'related_employees' => $relatedEmployees,

            // Interview details
            'scheduled_at' => $applicant->interview?->scheduled_at,
            'interview_type' => $applicant->interview?->interview_type,
            'duration' => $applicant->interview?->duration,
            'interviewer' => $applicant->interview?->interviewer,
            'location' => $applicant->interview?->location,
            'notes' => $applicant->interview?->notes,
        ];
    }
}