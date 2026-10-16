<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraineeWeeklyEvaluation extends Model
{
    protected $fillable = [
        'user_id',
        'week_number',
        'attendance',
        'knowledge',
        'practical_skills',
        'work_behavior',
        'safety_compliance',
        'overall',
        'notes',
        'recommendation',
        'evaluated_by',
        'evaluated_at',
    ];

    protected $casts = [
        'attendance' => 'decimal:2',
        'knowledge' => 'decimal:2',
        'practical_skills' => 'decimal:2',
        'work_behavior' => 'decimal:2',
        'safety_compliance' => 'decimal:2',
        'overall' => 'decimal:2',
        'evaluated_at' => 'datetime',
    ];

    public const RECOMMENDATIONS = ['continue', 'ready_final', 'early_termination'];

    public static function overallFor(array $scores): float
    {
        $keys = ['attendance', 'knowledge', 'practical_skills', 'work_behavior', 'safety_compliance'];
        $vals = array_map(fn ($k) => (float) ($scores[$k] ?? 0), $keys);

        return round(array_sum($vals) / count($vals), 2);
    }

    public function trainee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
