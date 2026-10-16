<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmTrainingEnrollment extends Model
{
    protected $table = 'hrm_training_enrollments';
    protected $fillable = ['training_id', 'user_id', 'status', 'progress', 'completed_at'];
    protected $casts = ['completed_at' => 'datetime'];

    public function training(): BelongsTo
    {
        return $this->belongsTo(HrmTraining::class, 'training_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
