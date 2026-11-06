<?php

namespace App\Models\Hrm;

use App\Services\Hrm\ApplicantApplicationHoldService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicantJobApplication extends Model
{
    protected $table = 'applicant_job_applications';
    protected $fillable = [
        'applicant_id', 'job_posting_id', 'status', 'cover_letter',
        'notice_period', 'availability_date', 'resume_path',
        'failed_stage', 'failed_at', 'rejection_reason',
        'is_on_hold', 'previous_status', 'hold_reason', 'held_by_application_id',
    ];
    protected $casts = [
        'availability_date' => 'date',
        'failed_at' => 'datetime',
        'is_on_hold' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (self $application) {
            ApplicantApplicationHoldService::afterCreated($application);
        });

        static::updated(function (self $application) {
            if ($application->wasChanged('status')) {
                ApplicantApplicationHoldService::afterStatusChange($application);
            }
        });
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(HrmJobPosting::class, 'job_posting_id');
    }

    public function heldByApplication(): BelongsTo
    {
        return $this->belongsTo(self::class, 'held_by_application_id');
    }

    public function holdings(): HasMany
    {
        return $this->hasMany(self::class, 'held_by_application_id');
    }

    public function scopeNotOnHold(Builder $query): Builder
    {
        return ApplicantApplicationHoldService::withoutHolds($query);
    }

    public function scopeOnHold(Builder $query): Builder
    {
        if (! ApplicantApplicationHoldService::columnsReady()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('is_on_hold', true);
    }

    public function isOnHold(): bool
    {
        return (bool) $this->is_on_hold;
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
