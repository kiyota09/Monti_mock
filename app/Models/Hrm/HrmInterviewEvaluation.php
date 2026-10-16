<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmInterviewEvaluation extends Model
{
    protected $table = 'hrm_interview_evaluations';
    protected $fillable = [
        'interview_id', 'communication_skills', 'relevant_skills', 'technical_knowledge',
        'problem_solving', 'work_experience', 'adaptability', 'teamwork', 'professionalism',
        'strengths', 'concerns', 'interview_notes', 'result', 'recommendation', 'evaluated_by',
    ];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(HrmInterview::class, 'interview_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Core\User::class, 'evaluated_by');
    }
}
