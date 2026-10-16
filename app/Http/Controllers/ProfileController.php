<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Hrm\Applicant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        // The employee's hiring application: linked at hire time via
        // hired_user_id, with an email fallback for accounts hired before
        // the link existed. Eager-loads everything the profile page shows
        // (personal info from the application + submitted ID card images).
        $applicant = $this->linkedApplicant($user);

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'application' => $applicant ? $this->formatApplication($applicant) : null,
        ]);
    }

    /**
     * The employee's hiring application: linked at hire time via
     * hired_user_id, with an email fallback for accounts hired before the
     * link existed.
     */
    private function linkedApplicant($user): ?Applicant
    {
        $applicant = Applicant::query()
            ->where('hired_user_id', $user->id)
            ->with(['documents', 'department', 'jobPosting', 'orgPosition', 'employmentType'])
            ->latest('id')
            ->first();

        if (! $applicant && $user->email) {
            $applicant = Applicant::query()
                ->where('email', $user->email)
                ->with(['documents', 'department', 'jobPosting', 'orgPosition', 'employmentType'])
                ->latest('id')
                ->first();
        }

        return $applicant;
    }

    /**
     * Let employees keep their own application personal details current
     * (moved address, newly issued government IDs, changed civil status or
     * emergency contact…). Hiring-process fields (position, status, module…)
     * stay HR-owned and are not editable here.
     */
    public function updateApplication(Request $request): RedirectResponse
    {
        $applicant = $this->linkedApplicant($request->user());

        if (! $applicant) {
            return Redirect::back()->withErrors([
                'error' => 'No hiring application is linked to this account yet.',
            ]);
        }

        $data = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string|max:255',
            'citizenship' => 'nullable|string|max:100',
            'age' => 'nullable|integer|min:0|max:150',
            'sex' => 'nullable|string|max:30',
            'contact_number' => 'nullable|string|max:30',
            'phone_number' => 'nullable|string|max:30',
            'civil_status' => 'nullable|string|max:50',
            'religion' => 'nullable|string|max:100',
            'languages' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric|min:0|max:500',
            'height' => 'nullable|numeric|min:0|max:300',
            'street_address' => 'nullable|string|max:255',
            'street_address_line2' => 'nullable|string|max:255',
            'barangay' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state_province' => 'nullable|string|max:100',
            'postal_zip_code' => 'nullable|string|max:20',
            'spouse_name' => 'nullable|string|max:255',
            'spouse_occupation' => 'nullable|string|max:255',
            'spouse_address' => 'nullable|string|max:255',
            'number_of_children' => 'nullable|integer|min:0|max:30',
            'children' => 'nullable|array',
            'children.*.name' => 'nullable|string|max:255',
            'children.*.dob' => 'nullable|string|max:50',
            'mother_name' => 'nullable|string|max:255',
            'mother_address' => 'nullable|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'father_address' => 'nullable|string|max:255',
            'emergency_name' => 'nullable|string|max:255',
            'emergency_relationship' => 'nullable|string|max:100',
            'emergency_phone' => 'nullable|string|max:30',
            'emergency_address' => 'nullable|string|max:255',
            'elementary_school' => 'nullable|string|max:255',
            'elementary_year' => 'nullable|string|max:50',
            'high_school' => 'nullable|string|max:255',
            'high_year' => 'nullable|string|max:50',
            'college' => 'nullable|string|max:255',
            'college_year' => 'nullable|string|max:50',
            'vocational' => 'nullable|string|max:255',
            'vocational_year' => 'nullable|string|max:50',
            'highest_education' => 'nullable|string|max:255',
            'school' => 'nullable|string|max:255',
            'course' => 'nullable|string|max:255',
            'graduation_year' => 'nullable|string|max:50',
            'special_skills' => 'nullable|string|max:1000',
            'machine_operation' => 'nullable|string|max:1000',
            'has_employment_record' => 'nullable|boolean',
            'employment_records' => 'nullable|array',
            'employment_records.*.company' => 'nullable|string|max:255',
            'employment_records.*.years' => 'nullable|string|max:100',
            'employment_records.*.salary' => 'nullable|string|max:100',
            'employment_records.*.position' => 'nullable|string|max:255',
            'employment_records.*.reason' => 'nullable|string|max:500',
            'previous_employment_company' => 'nullable|string|max:255',
            'previous_employment_when' => 'nullable|string|max:100',
            'previous_employment_position' => 'nullable|string|max:255',
            'previous_employment_department' => 'nullable|string|max:255',
            'referred_by' => 'nullable|string|max:255',
            'referred_by_address' => 'nullable|string|max:255',
            'sss_number' => 'nullable|string|max:30',
            'philhealth_number' => 'nullable|string|max:30',
            'pagibig_number' => 'nullable|string|max:30',
            'sss_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'philhealth_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'pagibig_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        // Drop fully-empty dynamic rows so blank lines don't pile up.
        foreach (['children', 'employment_records'] as $list) {
            if (isset($data[$list]) && is_array($data[$list])) {
                $data[$list] = array_values(array_filter($data[$list], function ($row) {
                    if (! is_array($row)) {
                        return $row !== null && $row !== '';
                    }
                    foreach ($row as $v) {
                        if ($v !== null && $v !== '') {
                            return true;
                        }
                    }
                    return false;
                }));
            }
        }

        foreach (['sss_file', 'philhealth_file', 'pagibig_file'] as $field) {
            if ($request->hasFile($field)) {
                if ($applicant->{$field}) {
                    Storage::disk('public')->delete($applicant->{$field});
                }
                $data[$field] = $request->file($field)->store('applicants/ids', 'public');
            } else {
                unset($data[$field]);
            }
        }

        $applicant->update($data);

        return Redirect::back()->with('status', 'application-updated');
    }

    /**
     * Shape an applicant record for the employee profile page: every
     * personal detail from the hiring application plus public URLs for the
     * submitted ID card images and documents.
     */
    private function formatApplication(Applicant $applicant): array
    {
        $file = fn (?string $path) => $path ? [
            'url' => Storage::url($path),
            'is_image' => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']),
        ] : null;

        return [
            'id' => $applicant->id,
            'status' => $applicant->status,
            'position_applied' => $applicant->position_applied,
            'expected_salary' => $applicant->expected_salary,
            'notice_period' => $applicant->notice_period,
            'textile_experience' => $applicant->textile_experience,
            'assigned_module' => $applicant->assigned_module,
            'applied_at' => $applicant->created_at?->toDateTimeString(),
            'job_posting' => $applicant->jobPosting?->title,
            'department' => $applicant->department?->name,
            'org_position' => $applicant->orgPosition?->name,
            'employment_type' => $applicant->employmentType?->name,
            'photo' => $file($applicant->image),

            'personal' => [
                'first_name' => $applicant->first_name,
                'middle_name' => $applicant->middle_name,
                'last_name' => $applicant->last_name,
                'suffix' => $applicant->suffix,
                'date_of_birth' => $applicant->date_of_birth?->toDateString(),
                'place_of_birth' => $applicant->place_of_birth,
                'citizenship' => $applicant->citizenship,
                'age' => $applicant->age,
                'sex' => $applicant->sex,
                'civil_status' => $applicant->civil_status,
                'weight' => $applicant->weight,
                'height' => $applicant->height,
                'religion' => $applicant->religion,
                'languages' => $applicant->languages,
                'email' => $applicant->email,
                'phone_number' => $applicant->phone_number,
                'contact_number' => $applicant->contact_number,
            ],
            'address' => [
                'street_address' => $applicant->street_address,
                'street_address_line2' => $applicant->street_address_line2,
                'barangay' => $applicant->barangay,
                'city' => $applicant->city,
                'state_province' => $applicant->state_province,
                'postal_zip_code' => $applicant->postal_zip_code,
            ],
            'government_ids' => [
                ['key' => 'sss', 'label' => 'SSS', 'number' => $applicant->sss_number, 'file' => $file($applicant->sss_file)],
                ['key' => 'philhealth', 'label' => 'PhilHealth', 'number' => $applicant->philhealth_number, 'file' => $file($applicant->philhealth_file)],
                ['key' => 'pagibig', 'label' => 'Pag-IBIG', 'number' => $applicant->pagibig_number, 'file' => $file($applicant->pagibig_file)],
            ],
            'family' => [
                'spouse_name' => $applicant->spouse_name,
                'spouse_occupation' => $applicant->spouse_occupation,
                'spouse_address' => $applicant->spouse_address,
                'number_of_children' => $applicant->number_of_children,
                'children' => $applicant->children,
                'mother_name' => $applicant->mother_name,
                'mother_address' => $applicant->mother_address,
                'father_name' => $applicant->father_name,
                'father_address' => $applicant->father_address,
                'related_employees' => $applicant->related_employees,
            ],
            'emergency' => [
                'name' => $applicant->emergency_name,
                'relationship' => $applicant->emergency_relationship,
                'phone' => $applicant->emergency_phone,
                'address' => $applicant->emergency_address,
            ],
            'education' => [
                'elementary_school' => $applicant->elementary_school,
                'elementary_year' => $applicant->elementary_year,
                'high_school' => $applicant->high_school,
                'high_year' => $applicant->high_year,
                'college' => $applicant->college,
                'college_year' => $applicant->college_year,
                'vocational' => $applicant->vocational,
                'vocational_year' => $applicant->vocational_year,
                'highest_education' => $applicant->highest_education,
                'school' => $applicant->school,
                'course' => $applicant->course,
                'graduation_year' => $applicant->graduation_year,
                'special_skills' => $applicant->special_skills,
                'machine_operation' => $applicant->machine_operation,
            ],
            'employment' => [
                'has_employment_record' => (bool) $applicant->has_employment_record,
                'employment_records' => $applicant->employment_records,
                'previous_employment_company' => $applicant->previous_employment_company,
                'previous_employment_when' => $applicant->previous_employment_when,
                'previous_employment_position' => $applicant->previous_employment_position,
                'previous_employment_department' => $applicant->previous_employment_department,
                'referred_by' => $applicant->referred_by,
                'referred_by_address' => $applicant->referred_by_address,
            ],
            'documents' => $applicant->documents->map(fn ($d) => [
                'id' => $d->id,
                'type' => $d->type,
                'original_name' => $d->original_name,
                'verified' => (bool) $d->verified,
                'file' => $file($d->file_path),
            ])->values()->all(),
        ];
    }

    /**
     * Update the user's profile information, including photo.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // 1. Fill the user model with validated data (name/email)
        $user->fill($request->validated());

        // 2. Handle Profile Photo Upload
        if ($request->hasFile('photo')) {
            // Delete the old photo from storage if it exists to save space
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            // Store the new file in 'profile-photos' folder within 'public' disk
            $path = $request->file('photo')->store('profile-photos', 'public');

            // Save the path to the database column
            $user->profile_photo_path = $path;
        }

        // 3. Reset email verification if email changed
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // 4. Save all changes to the database
        $user->save();

        /**
         * Redirect back to the dashboard.
         * This allows the app.vue modal's onSuccess callback to close the modal.
         */
        return Redirect::back()->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account — DISABLED by policy.
     * No user may delete their own account; only IT can suspend or
     * disable accounts (IT Access Control). The route is kept so old
     * clients fail closed with a clear message instead of a 404.
     */
    public function destroy(Request $request): RedirectResponse
    {
        return Redirect::back()->withErrors([
            'error' => 'Account deletion is disabled. Only IT can suspend or disable an account — please contact your IT administrator.',
        ]);
    }
}
