<?php

namespace App\Http\Controllers\Applicants\Portal;

use App\Http\Controllers\Controller;
use App\Models\Hrm\ApplicantJobApplication;
use App\Models\Hrm\HrmInterview;
use App\Support\TerminatedAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

/**
 * Official APPLICANTS profile + account
 * (Dashboard/APPLICANTS/Profile/index.vue, Account/index.vue).
 */
class ProfileController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Dashboard/APPLICANTS/Profile/index', [
            'applicant' => $this->shape($request->user('applicant')),
        ]);
    }

    public function update(Request $request)
    {
        $applicant = $request->user('applicant');
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'suffix' => 'nullable|string|max:30',
            'birth_date' => 'nullable|date',
            'place_of_birth' => 'nullable|string|max:255',
            'citizenship' => 'nullable|string|max:100',
            'religion' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:20|max:300',
            'height' => 'nullable|numeric|min:100|max:250',
            'gender' => 'nullable|string|max:20',
            'civil_status' => 'nullable|string|max:20',
            'email' => 'sometimes|email|max:255|unique:applicants,email,' . $applicant->id,
            'phone' => 'nullable|string|max:40',
            'street' => 'nullable|string|max:500',
            'barangay' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'highest_education' => 'nullable|string|max:255',
            'school' => 'nullable|string|max:255',
            'course' => 'nullable|string|max:255',
            'graduation_year' => 'nullable|string|max:10',
            'skills' => 'nullable|array',
            'skills.*' => 'nullable|string|max:100',
            'work_experience' => 'nullable|array',
            'elementary_school' => 'nullable|string|max:255',
            'elementary_year' => 'nullable|string|max:10',
            'high_school' => 'nullable|string|max:255',
            'high_year' => 'nullable|string|max:10',
            'college' => 'nullable|string|max:255',
            'college_year' => 'nullable|string|max:10',
            'vocational' => 'nullable|string|max:255',
            'vocational_year' => 'nullable|string|max:10',
            'special_skills' => 'nullable|string|max:2000',
            'emergency_contact.name' => 'nullable|string|max:255',
            'emergency_contact.relationship' => 'nullable|string|max:60',
            'emergency_contact.phone' => 'nullable|string|max:40',
            'emergency_contact.address' => 'nullable|string|max:500',
            'profile_photo' => 'nullable|image|max:4096',
            'resumes' => 'nullable|array',
            'resumes.*' => 'file|mimes:pdf,doc,docx|max:5120',
            'resume_to_delete' => 'nullable|array',
            'resume_to_delete.*' => 'string|max:500',
        ]);

        if (! empty($data['email']) && $data['email'] !== $applicant->email
            && TerminatedAccess::isApplicantBlockedByEmail($data['email'])) {
            return back()->withErrors(['email' => TerminatedAccess::APPLICANT_BLOCK_MESSAGE]);
        }
        $update = [
            'first_name' => $data['first_name'] ?? $applicant->first_name,
            'middle_name' => $data['middle_name'] ?? $applicant->middle_name,
            'last_name' => $data['last_name'] ?? $applicant->last_name,
            'suffix' => $data['suffix'] ?? $applicant->suffix,
            'date_of_birth' => $data['birth_date'] ?? $applicant->date_of_birth,
            'place_of_birth' => $data['place_of_birth'] ?? $applicant->place_of_birth,
            'citizenship' => $data['citizenship'] ?? $applicant->citizenship,
            'religion' => $data['religion'] ?? $applicant->religion,
            'weight' => $data['weight'] ?? $applicant->weight,
            'height' => $data['height'] ?? $applicant->height,
            'sex' => $data['gender'] ?? $applicant->sex,
            'civil_status' => $data['civil_status'] ?? $applicant->civil_status,
            'email' => $data['email'] ?? $applicant->email,
            'phone_number' => $data['phone'] ?? $applicant->phone_number,
            'street_address' => $data['street'] ?? $applicant->street_address,
            'barangay' => $data['barangay'] ?? $applicant->barangay,
            'city' => $data['city'] ?? $applicant->city,
            'state_province' => $data['province'] ?? $applicant->state_province,
            'postal_zip_code' => $data['zip_code'] ?? $applicant->postal_zip_code,
            'highest_education' => $data['highest_education'] ?? $applicant->highest_education,
            'school' => $data['school'] ?? $applicant->school,
            'course' => $data['course'] ?? $applicant->course,
            'graduation_year' => $data['graduation_year'] ?? $applicant->graduation_year,
            'elementary_school' => $data['elementary_school'] ?? $applicant->elementary_school,
            'elementary_year' => $data['elementary_year'] ?? $applicant->elementary_year,
            'high_school' => $data['high_school'] ?? $applicant->high_school,
            'high_year' => $data['high_year'] ?? $applicant->high_year,
            'college' => $data['college'] ?? $applicant->college,
            'college_year' => $data['college_year'] ?? $applicant->college_year,
            'vocational' => $data['vocational'] ?? $applicant->vocational,
            'vocational_year' => $data['vocational_year'] ?? $applicant->vocational_year,
            'special_skills' => array_key_exists('skills', $data)
                ? implode(', ', array_filter($data['skills'] ?? []))
                : ($data['special_skills'] ?? $applicant->special_skills),
            'emergency_name' => $data['emergency_contact']['name'] ?? $applicant->emergency_name,
            'emergency_relationship' => $data['emergency_contact']['relationship'] ?? $applicant->emergency_relationship,
            'emergency_phone' => $data['emergency_contact']['phone'] ?? $applicant->emergency_phone,
            'emergency_address' => $data['emergency_contact']['address'] ?? $applicant->emergency_address,
        ];
        if (array_key_exists('work_experience', $data)) {
            $update['employment_records'] = $data['work_experience'];
        }
        // Detect a file that PHP silently dropped (upload_max_filesize=2M by default).
        // In that case hasFile() is false and validation passes, so we must warn explicitly.
        $photoFile = $request->file('profile_photo');
        $photoError = $_FILES['profile_photo']['error'] ?? null;        if ($photoError === UPLOAD_ERR_INI_SIZE || $photoError === UPLOAD_ERR_FORM_SIZE) {
            return back()->withErrors(['profile_photo' => 'Photo is too large for the server (max 2MB). Please use a smaller image.']);
        }
        if ($request->hasFile('profile_photo') && $photoFile && $photoFile->isValid()) {
            Log::info('Applicant profile photo upload', [
                'applicant_id' => $applicant->id,
                'orig_name' => $photoFile->getClientOriginalName(),
                'mime' => $photoFile->getMimeType(),
                'size_kb' => round($photoFile->getSize() / 1024),
            ]);
            $storedPath = $photoFile->store('applicants/photos', 'public');
            if (! $storedPath) {
                return back()->withErrors(['profile_photo' => 'Failed to store photo. Please try again.']);
            }
            // Remove previous photo only AFTER the new one is safely stored.
            if ($applicant->profile_photo_path && $applicant->profile_photo_path !== $storedPath && Storage::disk('public')->exists($applicant->profile_photo_path)) {
                Storage::disk('public')->delete($applicant->profile_photo_path);
            }
            $update['profile_photo_path'] = $storedPath;
            // Keep legacy `image` column in sync so every HRM view
            // (cards, view modal, screening) can display the photo.
            $update['image'] = $storedPath;
        } elseif ($request->has('profile_photo') && ! $request->hasFile('profile_photo')) {
            Log::warning('Applicant profile photo missing from upload', [
                'applicant_id' => $applicant->id,
                'content_length' => $request->server('CONTENT_LENGTH'),
                'files_keys' => array_keys($request->allFiles()),
            ]);
        }
        $saved = $applicant->update($update);
        $applicant->refresh();
        Log::info('Applicant profile updated', [
            'applicant_id' => $applicant->id,
            'saved' => $saved,
            'profile_photo_path' => $applicant->profile_photo_path,
            'image' => $applicant->image,
            'had_new_photo' => isset($update['profile_photo_path']),
        ]);

        foreach ($request->file('resumes', []) as $file) {
            $applicant->documents()->create([
                'type' => 'resume',
                'file_path' => $file->store('applicants/documents', 'public'),
                'original_name' => $file->getClientOriginalName(),
            ]);
        }
        foreach ($data['resume_to_delete'] ?? [] as $path) {
            $applicant->documents()->where('original_name', $path)->orWhere('file_path', $path)->delete();
        }

        return back()->with('success', 'Profile saved.');
    }

    public function account(Request $request)
    {
        $applicant = $request->user('applicant');

        return Inertia::render('Dashboard/APPLICANTS/Account/index', [
            'applicant' => [
                'id' => $applicant->id,
                'email' => $applicant->email,
                'first_name' => $applicant->first_name,
                'last_name' => $applicant->last_name,
                'email_verified_at' => optional($applicant->email_verified_at)?->toISOString(),
                'created_at' => optional($applicant->created_at)->toISOString(),
                'updated_at' => optional($applicant->updated_at)->toISOString(),
            ],
            'stats' => [
                'applications_count' => $applicant->jobApplications()->count(),
                'interviews_count' => HrmInterview::where('applicant_id', $applicant->id)->count(),
            ],
        ]);
    }

    public function password(Request $request)
    {
        $applicant = $request->user('applicant');
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        if (! $applicant->password || ! Hash::check($data['current_password'], $applicant->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }
        $applicant->update(['password' => $data['password']]);

        return back()->with('success', 'Password updated.');
    }

    public function verify(Request $request)
    {
        $request->user('applicant')->update(['email_verified_at' => now()]);

        return back()->with('success', 'Email verified.');
    }

    public function deactivate(Request $request)
    {
        $applicant = $request->user('applicant');
        $applicant->update(['archived' => true, 'status' => 'Deactivated']);
        Auth::guard('applicant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('applicant.login')->with('message', 'Account deactivated.');
    }

    /**
     * Reactivate a deactivated account by email (ported from super_monti,
     * adapted to the applicants table).
     */
    public function reactivate(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|exists:applicants,email']);
        // Terminated employees can never reactivate the applicant portal —
        // not with the company email nor the personal email.
        if (TerminatedAccess::isApplicantBlockedByEmail($data['email'])) {
            return back()->withErrors(['email' => TerminatedAccess::APPLICANT_BLOCK_MESSAGE]);
        }
        $account = \App\Models\Hrm\Applicant::where('email', $data['email'])->first();
        if (! $account) {
            return back()->withErrors(['email' => 'Account not found.']);
        }
        if ($account->status === TerminatedAccess::TERMINATED_STATUS) {
            return back()->withErrors(['email' => TerminatedAccess::APPLICANT_BLOCK_MESSAGE]);
        }
        if (! $account->archived) {
            return back()->with('success', 'This account is already active.');
        }
        $account->update(['archived' => false, 'status' => 'Submitted']);

        return redirect()->route('applicant.login')->with('message', 'Account reactivated. You may now log in.');
    }

    /**
     * Update account email (ported from super_monti). Changing email resets
     * verification so the new address must be verified.
     */
    public function updateEmail(Request $request)
    {
        $applicant = $request->user('applicant');
        $data = $request->validate(['email' => 'nullable|email|max:255']);
        if (! empty($data['email']) && $data['email'] !== $applicant->email) {
            if (TerminatedAccess::isApplicantBlockedByEmail($data['email'])) {
                return back()->withErrors(['email' => TerminatedAccess::APPLICANT_BLOCK_MESSAGE]);
            }
            $exists = \App\Models\Hrm\Applicant::where('email', $data['email'])
                ->where('id', '!=', $applicant->id)->exists();
            if ($exists) {
                return back()->withErrors(['email' => 'That email address is already in use.']);
            }
            $applicant->update(['email' => $data['email'], 'email_verified_at' => null]);
        }

        return back()->with('success', 'Account updated successfully.');
    }

    /**
     * Recent account activity: merged interviews + job applications feed
     * (ported from super_monti, adapted to hrm_* tables).
     */
    public function activity(Request $request)
    {
        $applicant = $request->user('applicant');

        $interviews = HrmInterview::where('applicant_id', $applicant->id)
            ->orderByDesc('date')->limit(20)->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'type' => 'interview',
                'title' => ($i->position ?? 'Interview') . ' - ' . ($i->type ?? 'Interview'),
                'status' => $i->status,
                'date' => optional($i->date)?->toDateString(),
                'created_at' => optional($i->created_at)->toISOString(),
            ]);
        $applications = ApplicantJobApplication::with('jobPosting:id,title')
            ->where('applicant_id', $applicant->id)
            ->orderByDesc('created_at')->limit(20)->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => 'application',
                'title' => $a->jobPosting?->title ?? 'Job Application',
                'status' => $a->status,
                'date' => optional($a->created_at)?->toDateString(),
                'created_at' => optional($a->created_at)->toISOString(),
            ]);

        return response()->json([
            'activity' => $interviews->concat($applications)
                ->sortByDesc('created_at')->values()->take(50),
            'account' => [
                'email' => $applicant->email,
                'member_since' => optional($applicant->created_at)->toISOString(),
                'email_verified_at' => optional($applicant->email_verified_at)->toISOString(),
                'is_active' => ! (bool) $applicant->archived,
            ],
        ]);
    }

    private function shape($a): array
    {
        $resumes = $a->documents()->where('type', 'resume')->pluck('original_name')->filter()->values()->all();

        return [
            'id' => $a->id,
            'applicant_account_id' => $a->id,
            'first_name' => $a->first_name,
            'middle_name' => $a->middle_name,
            'last_name' => $a->last_name,
            'suffix' => $a->suffix,
            'birth_date' => optional($a->date_of_birth)?->toDateString(),
            'place_of_birth' => $a->place_of_birth,
            'citizenship' => $a->citizenship,
            'religion' => $a->religion,
            'weight' => $a->weight,
            'height' => $a->height,
            'gender' => $a->sex,
            'civil_status' => $a->civil_status,
            'email' => $a->email,
            'phone' => $a->phone_number,
            'street' => $a->street_address,
            'barangay' => $a->barangay,
            'city' => $a->city,
            'province' => $a->state_province,
            'zip_code' => $a->postal_zip_code,
            'highest_education' => $a->highest_education,
            'school' => $a->school ?? $a->college,
            'course' => $a->course,
            'graduation_year' => $a->graduation_year ?? $a->college_year,
            'skills' => array_filter(array_map('trim', explode(',', (string) ($a->special_skills ?? '')))),
            'work_experience' => $a->employment_records ?? [],
            'resumes' => $resumes,
            'resume_path' => $a->documents()->where('type', 'resume')->value('file_path'),
            'profile_photo_path' => $a->profile_photo_path,
            'elementary_school' => $a->elementary_school,
            'elementary_year' => $a->elementary_year,
            'high_school' => $a->high_school,
            'high_year' => $a->high_year,
            'college' => $a->college,
            'college_year' => $a->college_year,
            'vocational' => $a->vocational,
            'vocational_year' => $a->vocational_year,
            'special_skills' => $a->special_skills,
            'emergency_contact' => [
                'name' => $a->emergency_name,
                'relationship' => $a->emergency_relationship,
                'phone' => $a->emergency_phone,
                'address' => $a->emergency_address,
            ],
        ];
    }
}
