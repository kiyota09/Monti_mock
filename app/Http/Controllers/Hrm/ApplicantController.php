<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\ApplicantStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Models\Position;
use App\Traits\HasPagePermissions;

class ApplicantController extends Controller
{
    use HasPagePermissions;

    /**
     * Manual-entry application form (HRM_NEW Temp_applicationForm).
     * Submits to hrm.temp.application.submit (store).
     */
    public function tempForm()
    {
        return Inertia::render('Dashboard/HRM_NEW/Temp_applicationForm', [
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    /**
     * Display pending applications.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $applicants = Applicant::with('interview')
            ->where('archived', false)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($a) => $this->formatApplicant($a));

        $permissions = $this->getPagePermissionsForModule('HRM');

        return Inertia::render('Dashboard/HRM_NEW/Application', [
            'applicants' => $applicants,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Accept an application and assign it to a module for interview.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function accept(Request $request, $id)
    {
        $request->validate(['module' => 'required|in:HRM,ECO,CRM,SCM,MAN,PROJ,FIN,LOG,IT']);

        $applicant = Applicant::findOrFail($id);
        $from = $applicant->status;
        $applicant->update([
            'status' => 'Interview',
            'assigned_module' => $request->module,
            'archived' => false,
        ]);
        ApplicantStatusHistory::create([
            'applicant_id' => $applicant->id, 'from_status' => $from,
            'to_status' => 'Interview', 'changed_by' => $request->user()->id,
            'reason' => "Accepted → {$request->module} (HRM)",
        ]);

        return back()->with('message', "Applicant assigned to {$request->module} for interview.");
    }

    /**
     * Reject an application and archive it with a reason.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string']);

        $applicant = Applicant::findOrFail($id);
        $from = $applicant->status;
        $applicant->update([
            'status' => 'Rejected',
            'archived' => true,
            'rejection_reason' => $request->reason,
        ]);
        ApplicantStatusHistory::create([
            'applicant_id' => $applicant->id, 'from_status' => $from,
            'to_status' => 'Rejected', 'changed_by' => $request->user()->id,
            'reason' => $request->reason,
        ]);

        return back()->with('message', 'Applicant rejected and archived.');
    }

    /**
     * Display all rejected applications (archived).
     *
     * @return \Inertia\Response
     */
    public function rejected()
    {
        $rejectedItems = Applicant::where('archived', true)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn($a) => $this->formatApplicant($a));

        $permissions = $this->getPagePermissionsForModule('HRM');

        // FIX: Use correct path and prop name (rejectedItems)
        return Inertia::render('Dashboard/HRM_NEW/Reject', [
            'rejectedItems' => $rejectedItems,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a new applicant (manual entry by HRM).
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // dd($request->all());
        
        // Validation rules for the complete application form
        $validated = $request->validate([
            // Personal & Contact
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'image' => 'nullable|image|max:2048',
            'email' => 'required|email|unique:applicants,email',
            'phone_number' => 'required|string',
            'street_address' => 'required|string',
            'street_address_line2' => 'nullable|string',
            'city' => 'required|string',
            'state_province' => 'required|string',
            'postal_zip_code' => 'required|string',
            'position_applied' => 'required|string',
            // 'expected_salary' => 'nullable|numeric',
            'notice_period' => 'required|string|in:Immediate,15_Days,30_Days,60_Days',

            // 'textile_experience' => 'nullable|string',

            // Personal details
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string',
            'citizenship' => 'nullable|string',
            'weight' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'civil_status' => 'nullable|string',
            'sex' => 'nullable|string',
            'religion' => 'nullable|string',
            'contact_number' => 'nullable|string',

            // Government IDs
            'sss_number' => 'nullable|string',
            'sss_file' => 'nullable|image|max:2048',
            'philhealth_number' => 'nullable|string',
            'philhealth_file' => 'nullable|image|max:2048',
            'pagibig_number' => 'nullable|string',
            'pagibig_file' => 'nullable|image|max:2048',

            // Family
            'spouse_name' => 'nullable|string',
            'spouse_occupation' => 'nullable|string',
            'spouse_address' => 'nullable|string',
            'number_of_children' => 'nullable|integer',
            'children' => 'nullable|array',
            'mother_name' => 'nullable|string',
            'mother_address' => 'nullable|string',
            'father_name' => 'nullable|string',
            'father_address' => 'nullable|string',
            'languages' => 'nullable|string',

            // Emergency
            'emergency_name' => 'nullable|string',
            'emergency_relationship' => 'nullable|string',
            'emergency_phone' => 'nullable|string',
            'emergency_address' => 'nullable|string',

            // Education & Skills
            'elementary_school' => 'nullable|string',
            'elementary_year' => 'nullable|string',
            'high_school' => 'nullable|string',
            'high_year' => 'nullable|string',
            'college' => 'nullable|string',
            'college_year' => 'nullable|string',
            'vocational' => 'nullable|string',
            'vocational_year' => 'nullable|string',
            'special_skills' => 'nullable|string',
            'has_employment_record' => 'nullable|boolean',
            'employment_records' => 'nullable|array',
            'machine_operation' => 'nullable|string',
            'referred_by' => 'nullable|string',
            'referred_by_address' => 'nullable|string',
            'previous_employment_company' => 'nullable|string',
            'previous_employment_when' => 'nullable|string',
            'previous_employment_position' => 'nullable|string',
            'previous_employment_department' => 'nullable|string',
            'related_employees' => 'nullable|string',
        ]);

        // Handle file uploads
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('applicants/images', 'public');
        }
        if ($request->hasFile('sss_file')) {
            $validated['sss_file'] = $request->file('sss_file')->store('applicants/ids', 'public');
        }
        if ($request->hasFile('philhealth_file')) {
            $validated['philhealth_file'] = $request->file('philhealth_file')->store('applicants/ids', 'public');
        }
        if ($request->hasFile('pagibig_file')) {
            $validated['pagibig_file'] = $request->file('pagibig_file')->store('applicants/ids', 'public');
        }

        // Set default values
        $validated['status'] = 'pending';
        $validated['archived'] = false;

        // Create the applicant
    
        $applicant = Applicant::create($validated);

        return back()->with('message', 'Applicant added successfully.');
    }

    /**
     * Format applicant data for consistent output.
     *
     * @param  \App\Models\Applicant  $applicant
     * @return array
     */
    private function formatApplicant($applicant)
    {
        return [
            'id' => $applicant->id,
            'name' => $applicant->name, // Using the getNameAttribute() from your model
            'first_name' => $applicant->first_name,
            'middle_name' => $applicant->middle_name,
            'last_name' => $applicant->last_name,
            'image' => $applicant->image,
            // binago
'image_url' => $applicant->image ? Storage::url($applicant->image) : null, // Using the getImageUrlAttribute()

            // Contact & Address
            'email' => $applicant->email,
            'phone_number' => $applicant->phone_number,
            'contact_number' => $applicant->contact_number,
            'street_address' => $applicant->street_address,
            'street_address_line2' => $applicant->street_address_line2,
            'city' => $applicant->city,
            'state_province' => $applicant->state_province,
            'postal_zip_code' => $applicant->postal_zip_code,

            // Application Details
            'position_applied' => $applicant->position_applied,
            'expected_salary' => $applicant->expected_salary,
            'notice_period' => $applicant->notice_period,
            'textile_experience' => $applicant->textile_experience,
            'status' => $applicant->status,
            'assigned_module' => $applicant->assigned_module,
            'rejection_reason' => $applicant->rejection_reason,
            'interview_feedback' => $applicant->interview_feedback,
            'archived' => $applicant->archived,

            // Personal Details
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

            // Government IDs
            'sss_number' => $applicant->sss_number,
            'philhealth_number' => $applicant->philhealth_number,
            'pagibig_number' => $applicant->pagibig_number,

            // File URLs (using the ternary operator you had, though your model accessors could also do this!)
            'sss_file_url' => $applicant->sss_file ? Storage::url($applicant->sss_file) : null,
            'philhealth_file_url' => $applicant->philhealth_file ? Storage::url($applicant->philhealth_file) : null,
            'pagibig_file_url' => $applicant->pagibig_file ? Storage::url($applicant->pagibig_file) : null,

            // Family
            'spouse_name' => $applicant->spouse_name,
            'spouse_occupation' => $applicant->spouse_occupation,
            'spouse_address' => $applicant->spouse_address,
            'number_of_children' => $applicant->number_of_children,
            'children' => $applicant->children,
            'mother_name' => $applicant->mother_name,
            'mother_address' => $applicant->mother_address,
            'father_name' => $applicant->father_name,
            'father_address' => $applicant->father_address,

            // Emergency Contact
            'emergency_name' => $applicant->emergency_name,
            'emergency_relationship' => $applicant->emergency_relationship,
            'emergency_phone' => $applicant->emergency_phone,
            'emergency_address' => $applicant->emergency_address,

            // Education & Skills
            'elementary_school' => $applicant->elementary_school,
            'elementary_year' => $applicant->elementary_year,
            'high_school' => $applicant->high_school,
            'high_year' => $applicant->high_year,
            'college' => $applicant->college,
            'college_year' => $applicant->college_year,
            'vocational' => $applicant->vocational,
            'vocational_year' => $applicant->vocational_year,
            'special_skills' => $applicant->special_skills,
            'machine_operation' => $applicant->machine_operation,

            // Employment History
            'has_employment_record' => $applicant->has_employment_record,
            'employment_records' => $applicant->employment_records,
            'previous_employment_company' => $applicant->previous_employment_company,
            'previous_employment_when' => $applicant->previous_employment_when,
            'previous_employment_position' => $applicant->previous_employment_position,
            'previous_employment_department' => $applicant->previous_employment_department,

            // Referrals & Relations
            'referred_by' => $applicant->referred_by,
            'referred_by_address' => $applicant->referred_by_address,
            'related_employees' => $applicant->related_employees,

            // Timestamps
            'created_at' => $applicant->created_at,
            'updated_at' => $applicant->updated_at,
        ];
    }
}