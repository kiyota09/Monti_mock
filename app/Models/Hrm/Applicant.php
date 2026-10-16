<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Applicant extends Authenticatable
{
    use Notifiable;
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        // Personal & Contact
        'first_name', 'middle_name', 'last_name', 'image', 'email', 'phone_number',
        'street_address', 'street_address_line2', 'city', 'state_province', 'postal_zip_code',
        'position_applied', 'expected_salary', 'notice_period', 'textile_experience',
        'status', 'sss_file', 'philhealth_file', 'pagibig_file',

        // New personal fields
        'date_of_birth', 'place_of_birth', 'citizenship', 'weight', 'height',
        'civil_status', 'sex', 'age', 'religion', 'contact_number',
        'sss_number', 'philhealth_number', 'pagibig_number',

        // Family
        'spouse_name', 'spouse_occupation', 'spouse_address', 'number_of_children', 'children',
        'mother_name', 'mother_address', 'father_name', 'father_address', 'languages',

        // Emergency
        'emergency_name', 'emergency_relationship', 'emergency_phone', 'emergency_address',

        // Education & Skills (individual columns, not JSON)
        'elementary_school', 'elementary_year', 'high_school', 'high_year',
        'college', 'college_year', 'vocational', 'vocational_year',
        'special_skills', 'has_employment_record', 'employment_records',
        'machine_operation', 'referred_by', 'referred_by_address',

        // Previous employment details (replaces the old 'previous_employment' text column)
        'previous_employment_company', 'previous_employment_when',
        'previous_employment_position', 'previous_employment_department',

        'related_employees',

        // Workflow
        'assigned_module', 'archived', 'rejection_reason', 'interview_feedback',
        // APPLICANTS module: HRM_NEW links (nullable FKs)
        'job_posting_id', 'department_id', 'org_position_id', 'employment_type_id',
        'assigned_hr_id', 'hired_user_id',
        // Applicant portal profile columns (Dashboard/APPLICANTS/Profile)
        'suffix', 'barangay', 'highest_education', 'school', 'course',
        'graduation_year', 'profile_photo_path', 'email_verified_at',
        // Applicant email-OTP verification (Auth-1 style OTP flow)
        'email_otp', 'email_otp_expires_at', 'email_otp_attempts',
        'email_otp_locked_until', 'email_otp_lock_count', 'email_otp_sent_at',
        // Applicant portal credentials
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_otp',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
        'email_otp_expires_at' => 'datetime',
        'email_otp_attempts' => 'integer',
        'email_otp_locked_until' => 'datetime',
        'email_otp_lock_count' => 'integer',
        'email_otp_sent_at' => 'datetime',
        'expected_salary' => 'decimal:2',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'age' => 'integer',
        'number_of_children' => 'integer',
        'children' => 'array',
        'has_employment_record' => 'boolean',
        'employment_records' => 'array',
        'related_employees' => 'array',
        'archived' => 'boolean',
        'date_of_birth' => 'date',
    ];

    /**
     * Get the applicant's full name.
     */
    public function getNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Accessor for profile image URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    /**
     * Accessor for SSS file URL.
     */
    public function getSssFileUrlAttribute(): ?string
    {
        return $this->sss_file ? asset('storage/'.$this->sss_file) : null;
    }

    /**
     * Accessor for PhilHealth file URL.
     */
    public function getPhilhealthFileUrlAttribute(): ?string
    {
        return $this->philhealth_file ? asset('storage/'.$this->philhealth_file) : null;
    }

    /**
     * Accessor for Pag-IBIG file URL.
     */
    public function getPagibigFileUrlAttribute(): ?string
    {
        return $this->pagibig_file ? asset('storage/'.$this->pagibig_file) : null;
    }

    /**
     * Relationship: An applicant has one interview schedule.
     */
    public function interview(): HasOne
    {
        return $this->hasOne(Interview::class);
    }

    // APPLICANTS module: HRM_NEW links
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(HrmJobPosting::class, 'job_posting_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrmDepartment::class, 'department_id');
    }

    public function orgPosition(): BelongsTo
    {
        return $this->belongsTo(HrmPosition::class, 'org_position_id');
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(HrmEmploymentType::class, 'employment_type_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicantDocument::class, 'applicant_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ApplicantStatusHistory::class, 'applicant_id')->latest();
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(HrmApplicationScreening::class, 'applicant_id')->latest();
    }

    public function hrmInterviews(): HasMany
    {
        return $this->hasMany(HrmInterview::class, 'applicant_id')->latest();
    }

    // Official APPLICANTS portal relations
    public function jobApplications(): HasMany
    {
        return $this->hasMany(ApplicantJobApplication::class, 'applicant_id')->latest();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ApplicantNotification::class, 'applicant_id')->latest();
    }
}
