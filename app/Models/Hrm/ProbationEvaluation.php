<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProbationEvaluation extends Model
{
    protected $table = 'hrm_probation_evaluations';

    protected $fillable = [
        'user_id', 'evaluator_id', 'reviewer_id', 'period_start', 'period_end', 'period_label', 'due_date',
        'scores', 'overall_score', 'comments', 'strengths', 'improvements', 'recommendation',
        'status', 'review_notes', 'evaluation_type', 'template_id', 'template_snapshot', 'manager_id',
        'employment_status_snapshot', 'employment_type_snapshot', 'classification', 'hr_recommendation',
        'management_decision', 'acknowledged_at', 'employee_feedback',
    ];

    protected function casts(): array
    {
        return ['scores' => 'array', 'template_snapshot' => 'array', 'acknowledged_at' => 'datetime', 'period_start' => 'date', 'period_end' => 'date', 'due_date' => 'date'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function evaluator(): BelongsTo { return $this->belongsTo(User::class, 'evaluator_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
}
