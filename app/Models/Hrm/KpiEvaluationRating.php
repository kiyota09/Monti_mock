<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiEvaluationRating extends Model
{
    protected $table = 'hrm_kpi_evaluation_ratings';
    protected $guarded = ['id'];
    protected function casts(): array { return ['objective_data' => 'array']; }
    public function evaluation(): BelongsTo { return $this->belongsTo(KpiEvaluation::class, 'evaluation_id'); }
    public function evidence(): HasMany { return $this->hasMany(KpiEvaluationEvidence::class, 'evaluation_rating_id'); }
}
