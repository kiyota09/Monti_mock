<?php

namespace App\Models\Ord;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturn extends Model
{
    use HasFactory;

    public const TYPES = ['shortage', 'reject', 'client_return', 'overrun'];

    public const STATUSES = ['pending', 'inspected', 'credited', 'rejected', 'closed'];

    /**
     * RMA resolution state machine. Claims can only move forward:
     * pending -> inspected -> credited|rejected -> closed.
     */
    public const TRANSITIONS = [
        'pending' => ['inspected'],
        'inspected' => ['credited', 'rejected'],
        'credited' => ['closed'],
        'rejected' => ['closed'],
        'closed' => [],
    ];

    public static function allowedTransitions(?string $from): array
    {
        return self::TRANSITIONS[(string) $from] ?? [];
    }

    protected $fillable = [
        'return_number',
        'sales_order_id',
        'type',
        'quantity',
        'reason',
        'status',
        'created_by',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
