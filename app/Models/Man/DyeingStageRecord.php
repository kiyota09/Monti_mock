<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class DyeingStageRecord extends Model
{
    protected $fillable = [
        'dyeing_batch_fabric_id', 'stage', 'attempt', 'operator_id', 'code', 'review_status',
        'operator_remarks', 'supervisor_remarks', 'reviewed_by', 'processed_at', 'reviewed_at', 'dye_job_id', 'bleaching_job_id',
        'submission_code', 'materials_used',
    ];

    protected $casts = ['processed_at' => 'datetime', 'reviewed_at' => 'datetime', 'materials_used' => 'array'];

    public function batchFabric() { return $this->belongsTo(DyeingBatchFabric::class, 'dyeing_batch_fabric_id'); }
    public function operator() { return $this->belongsTo(User::class, 'operator_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
