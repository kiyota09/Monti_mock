<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TraineeGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'skills_performance',
        'behaviour',
        'technicals',
        'safety_awareness',
        'productivity',
        'total_percentage',
        'passed_to_hr',
        // Weighted training evaluation (Training page)
        'attendance',
        'knowledge',
        'practical_skills',
        'work_behavior',
        'safety_compliance',
        'final_grade',
        'evaluation_status',
        'remarks',
        'evaluated_at',
        'evaluated_by',
        // Reporting-manager recommendations (My Assigned Trainees page)
        'manager_recommendation',
        'final_notes',
        'weekly_recommendation',
        'weekly_notes',
        // HR review & decision (Training List > Trainee Training Details)
        'hr_review_status',
        'hr_reviewer_id',
        'hr_review_notes',
        'hr_reviewed_at',
        // Retraining cycle
        'retraining',
        'retraining_count',
        'retraining_start_date',
        'retraining_end_date',
        'retraining_reason',
        'retraining_notes',
    ];
    protected $casts = [
        'passed_to_hr' => 'boolean',
        'retraining' => 'boolean',
        'retraining_start_date' => 'date',
        'retraining_end_date' => 'date',
        'evaluated_at' => 'datetime',
        'hr_reviewed_at' => 'datetime',
        'attendance' => 'decimal:2',
        'knowledge' => 'decimal:2',
        'practical_skills' => 'decimal:2',
        'work_behavior' => 'decimal:2',
        'safety_compliance' => 'decimal:2',
        'final_grade' => 'decimal:2',
    ];

    /** Criteria weights used by the Training evaluation modal (must sum to 1). */
    public const EVALUATION_WEIGHTS = [
        'attendance' => 0.20,
        'knowledge' => 0.25,
        'practical_skills' => 0.30,
        'work_behavior' => 0.15,
        'safety_compliance' => 0.10,
    ];

    public const PASSING_GRADE = 75;

    public static function computeFinalGrade(array $scores): float
    {
        $total = 0.0;
        foreach (self::EVALUATION_WEIGHTS as $key => $weight) {
            $total += ((float) ($scores[$key] ?? 0)) * $weight;
        }

        return round($total, 2);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
