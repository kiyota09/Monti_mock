<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantJobApplication extends Model
{
    protected $table = 'applicant_job_applications';
    protected $fillable = [
        'applicant_id', 'job_posting_id', 'status', 'cover_letter',
        'notice_period', 'availability_date', 'resume_path',
        'failed_stage', 'failed_at', 'rejection_reason',
    ];
    protected $casts = ['availability_date' => 'date', 'failed_at' => 'datetime'];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(HrmJobPosting::class, 'job_posting_id');
    }

    public function getIsWithdrawnAttribute(): bool
    {
        return strtolower((string) $this->status) === 'withdrawn';
    }

    /**
     * The resume document attached to THIS job application.
     *
     * resume_path may hold either a storage path (fresh upload) or the
     * document's original file name (picked from the applicant's library),
     * so match against both.
     */
    public function resolveResumeDocument(): ?ApplicantDocument
    {
        if (! $this->resume_path || ! $this->applicant) {
            return null;
        }
        $docs = $this->applicant->documents()->where('type', 'resume')->get();

        return $docs->firstWhere('file_path', $this->resume_path)
            ?? $docs->firstWhere('original_name', $this->resume_path);
    }
}
