<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Manpower agency Monti Corp works with (HRM > Agencies).
 *
 * Stored status is Active|Suspended|Terminated. The *effective* status also
 * reports Pending while the contract start date is in the future.
 */
class Agency extends Model
{
    public const STATUS_ACTIVE = 'Active';

    public const STATUS_SUSPENDED = 'Suspended';

    public const STATUS_TERMINATED = 'Terminated';

    public const STATUS_PENDING = 'Pending';

    protected $fillable = [
        'code', 'name', 'tin', 'address',
        'dole_number', 'dole_expiry',
        'contract_start', 'contract_end',
        'service_fee_type', 'service_fee_value',
        'contact_name', 'contact_email', 'contact_phone',
        'status', 'terminated_at',
    ];

    protected $casts = [
        'dole_expiry' => 'date',
        'contract_start' => 'date',
        'contract_end' => 'date',
        'service_fee_value' => 'decimal:2',
        'terminated_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(AgencyUser::class, 'agency_id');
    }

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class, 'agency_id');
    }

    public function manpowerRequests(): HasMany
    {
        return $this->hasMany(ManpowerRequest::class, 'agency_id');
    }

    public function billingStatements(): HasMany
    {
        return $this->hasMany(BillingStatement::class, 'agency_id');
    }

    /**
     * A terminated agency with a Paid or Cancelled statement is fully
     * locked: nothing new (attendance, statements) may be added.
     */
    public function getHasFinalStatementAttribute(): bool
    {
        return $this->billingStatements()
            ->whereIn('status', [BillingStatement::STATUS_PAID, BillingStatement::STATUS_CANCELLED])
            ->exists();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(AgencyActivity::class, 'agency_id')->latest();
    }

    /**
     * Effective status: stored Suspended/Terminated always win; an Active
     * agency whose contract has not started yet reads as Pending.
     */
    public function getEffectiveStatusAttribute(): string
    {
        if (in_array($this->status, [self::STATUS_SUSPENDED, self::STATUS_TERMINATED], true)) {
            return $this->status;
        }
        if ($this->contract_start && Carbon::parse($this->contract_start)->startOfDay()->gt(Carbon::today())) {
            return self::STATUS_PENDING;
        }

        return self::STATUS_ACTIVE;
    }

    /**
     * Whether the contract end or DOLE expiry falls within the next 30
     * days (or is already past) — those rows highlight yellow.
     */
    public function getExpiringSoonAttribute(): bool
    {
        $limit = Carbon::today()->addDays(30);

        foreach ([$this->contract_end, $this->dole_expiry] as $date) {
            if (! $date) {
                continue;
            }
            if (Carbon::parse($date)->startOfDay()->lte($limit)) {
                return true;
            }
        }

        return false;
    }
}
