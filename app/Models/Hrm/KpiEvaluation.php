<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KpiEvaluation extends Model
{
    protected $table = 'hrm_kpi_evaluations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'template_snapshot' => 'array', 'classification_snapshot' => 'array',
            'period_start' => 'date', 'period_end' => 'date', 'due_date' => 'date',
            'submitted_at' => 'datetime', 'completed_at' => 'datetime', 'acknowledged_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function evaluator(): BelongsTo { return $this->belongsTo(User::class, 'evaluator_id'); }
    public function manager(): BelongsTo { return $this->belongsTo(User::class, 'manager_id'); }
    public function template(): BelongsTo { return $this->belongsTo(KpiTemplate::class, 'template_id'); }
    public function ratings(): HasMany { return $this->hasMany(KpiEvaluationRating::class, 'evaluation_id'); }
    public function reviews(): HasMany { return $this->hasMany(KpiEvaluationReview::class, 'evaluation_id'); }
    public function acknowledgment(): HasOne { return $this->hasOne(KpiEmployeeAcknowledgment::class, 'evaluation_id'); }
    public function auditLogs(): HasMany { return $this->hasMany(KpiAuditLog::class, 'evaluation_id'); }
}
