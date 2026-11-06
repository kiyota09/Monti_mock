<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgencyReferral extends Model
{
    protected $table = 'hrm_agency_referrals';

    protected $fillable = [
        'applicant_job_application_id', 'applicant_id', 'job_posting_id', 'agency_id',
        'status', 'referral_reason', 'agency_instructions', 'consent_verified',
        'monti_approved_feedback', 'shared_documents', 'expected_response_date', 'referral_date', 'sent_at',
        'agency_response', 'agency_feedback', 'response_received_at', 'monti_decision',
        'decision_reason', 'decided_by', 'decided_at', 'created_by',
    ];

    protected $casts = [
        'consent_verified' => 'boolean',
        'shared_documents' => 'array',
        'expected_response_date' => 'date',
        'referral_date' => 'date',
        'sent_at' => 'datetime',
        'response_received_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(ApplicantJobApplication::class, 'applicant_job_application_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(HrmJobPosting::class, 'job_posting_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(AgencyReferralHistory::class, 'agency_referral_id')->latest();
    }
}
