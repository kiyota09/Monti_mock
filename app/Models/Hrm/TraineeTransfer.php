<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraineeTransfer extends Model
{
    protected $fillable = [
        'user_id',
        'from_department',
        'from_position',
        'to_department',
        'to_department_id',
        'to_position',
        'to_position_id',
        'effective_date',
        'reason',
        'new_training_required',
        'training_result',
        'previous_grade',
        'transferred_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'new_training_required' => 'boolean',
        'previous_grade' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
