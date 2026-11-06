<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiPerformanceImprovementPlan extends Model
{
    protected $table = 'hrm_kpi_pips';
    protected $guarded = ['id'];
    protected function casts(): array { return ['start_date' => 'date', 'target_date' => 'date', 'review_date' => 'date']; }
    public function employee(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function evaluation(): BelongsTo { return $this->belongsTo(KpiEvaluation::class, 'evaluation_id'); }
    public function supervisor(): BelongsTo { return $this->belongsTo(User::class, 'supervisor_id'); }
    public function progress(): HasMany { return $this->hasMany(KpiPipProgress::class, 'pip_id'); }
}
