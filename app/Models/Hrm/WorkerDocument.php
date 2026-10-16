<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Supporting file attached to a worker (resume, gov ID, clearance,
 * medical, training cert, other). HR verifies each file as
 * Pending | Verified | Rejected | Expired.
 */
class WorkerDocument extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'Pending';

    public const STATUS_VERIFIED = 'Verified';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUS_EXPIRED = 'Expired';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
    ];

    public const TYPES = [
        'resume' => 'Resume / CV',
        'gov_id' => 'Government ID',
        'clearance' => 'Clearance',
        'medical' => 'Medical Certificate',
        'training_cert' => 'Training / Certification',
        'position_requirement' => 'Position requirement',
        'other' => 'Other requirement',
    ];

    protected $fillable = [
        'worker_id',
        'doc_type',
        'label',
        'file_path',
        'original_name',
        'mime',
        'size',
        'expiry_date',
        'issue_date',
        'status',
        'review_note',
        'verified_by_name',
        'verified_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'issue_date' => 'date',
        'verified_at' => 'datetime',
        'size' => 'integer',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }
}
