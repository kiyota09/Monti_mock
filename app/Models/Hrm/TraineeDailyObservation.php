<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraineeDailyObservation extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'knowledge',
        'practical_skills',
        'work_behavior',
        'safety_compliance',
        'knowledge_notes',
        'practical_skills_notes',
        'work_behavior_notes',
        'safety_compliance_notes',
        'notes',
        'evaluated_by',
        'evaluated_at',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'knowledge' => 'decimal:2',
        'practical_skills' => 'decimal:2',
        'work_behavior' => 'decimal:2',
        'safety_compliance' => 'decimal:2',
        'evaluated_at' => 'datetime',
    ];

    public function trainee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
