<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiEmployeeAcknowledgment extends Model
{
    protected $table = 'hrm_kpi_employee_acknowledgments';
    protected $guarded = ['id'];
    protected function casts(): array { return ['agrees' => 'boolean', 'acknowledged_at' => 'datetime']; }
    public function evaluation(): BelongsTo { return $this->belongsTo(KpiEvaluation::class, 'evaluation_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(User::class, 'employee_id'); }
}
