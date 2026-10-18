<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Agency-supplied worker (HRM > Workers).
 *
 * Available ⇄ Deployed flips automatically on assign/release. Inactive is
 * manual (reversible). Blacklisted needs a reason and HR/IT to lift.
 */
class Worker extends Model
{
    public const STATUS_AVAILABLE = 'Available';

    public const STATUS_DEPLOYED = 'Deployed';

    public const STATUS_INACTIVE = 'Inactive';

    public const STATUS_BLACKLISTED = 'Blacklisted';

    public const STATUS_PENDING = 'Pending';

    public const STATUS_CORRECTION = 'For Correction';

    protected $fillable = [
        'code', 'agency_id', 'name', 'birth_date', 'gender', 'phone', 'email', 'address',
        'resume_path', 'resume_original_name',
        'education', 'work_experience', 'skills', 'trainings_certifications',
        'position_skill', 'years_experience', 'proposed_department',
        'availability', 'daily_rate', 'employment_arrangement',
        'gov_id_type', 'gov_id_number', 'clearance_expiry',
        'emergency_name', 'emergency_relationship', 'emergency_phone',
        'status', 'blacklist_reason',
        'pending_note', 'consent_given', 'consent_at', 'privacy_notice_version',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'clearance_expiry' => 'date',
        'daily_rate' => 'decimal:2',
        'years_experience' => 'decimal:1',
        'consent_given' => 'boolean',
        'consent_at' => 'datetime',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(WorkerDocument::class, 'worker_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ManpowerAssignment::class, 'worker_id');
    }

    public function activeAssignment(): HasMany
    {
        return $this->hasMany(ManpowerAssignment::class, 'worker_id')->whereNull('released_at');
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(ManpowerAssignment::class, 'worker_id')->whereNull('released_at');
    }

    /**
     * Work shifts assigned to this agency worker (hrm_shift_worker pivot).
     */
    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(HrmShift::class, 'hrm_shift_worker', 'worker_id', 'shift_id')->withTimestamps();
    }

    public function getClearanceExpiredAttribute(): bool
    {
        if (! $this->clearance_expiry) {
            return false;
        }

        return Carbon::parse($this->clearance_expiry)->startOfDay()->lt(Carbon::today());
    }

    public function getClearanceExpiringSoonAttribute(): bool
    {
        if (! $this->clearance_expiry) {
            return false;
        }

        return Carbon::parse($this->clearance_expiry)->startOfDay()->lte(Carbon::today()->addDays(30));
    }
}
