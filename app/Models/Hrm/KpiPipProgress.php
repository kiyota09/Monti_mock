<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiPipProgress extends Model
{
    protected $table = 'hrm_kpi_pip_progress';
    protected $guarded = ['id'];
    protected function casts(): array { return ['recorded_on' => 'date']; }
    public function plan(): BelongsTo { return $this->belongsTo(KpiPerformanceImprovementPlan::class, 'pip_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
