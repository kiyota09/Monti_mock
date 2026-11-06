<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiEvaluationEvidence extends Model
{
    protected $table = 'hrm_kpi_evaluation_evidence';
    protected $guarded = ['id'];
    public function rating(): BelongsTo { return $this->belongsTo(KpiEvaluationRating::class, 'evaluation_rating_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
