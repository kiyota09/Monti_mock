<?php

namespace App\Models\Ord;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'orderable_type',
        'orderable_id',
        'order_type',
        'order_id',
        'from_status',
        'to_status',
        'changed_by',
        'notes',
    ];

    public function orderable(): MorphTo
    {
        return $this->morphTo();
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
