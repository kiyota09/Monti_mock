<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiEvaluationReview extends Model
{
    protected $table = 'hrm_kpi_evaluation_reviews';
    protected $guarded = ['id'];
    public function evaluation(): BelongsTo { return $this->belongsTo(KpiEvaluation::class, 'evaluation_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
}
