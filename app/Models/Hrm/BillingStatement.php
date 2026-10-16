<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingStatement extends Model
{
    public const STATUS_DRAFT = 'Draft';

    public const STATUS_ISSUED = 'Issued';

    public const STATUS_PAID = 'Paid';

    public const STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'code', 'agency_id', 'period_start', 'period_end', 'status',
        'labor_cost', 'service_fee', 'total',
        'paid_at', 'payment_reference', 'cancel_reason',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'labor_cost' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'billed_statement_id');
    }

    /**
     * A final statement (Paid or Cancelled) on a terminated agency locks
     * the agency: nothing new may be added afterwards.
     */
    public function getIsFinalAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED], true);
    }
}
