<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiAuditLog extends Model
{
    protected $table = 'hrm_kpi_audit_logs';
    protected $guarded = ['id'];
    protected function casts(): array { return ['details' => 'array']; }
    public function evaluation(): BelongsTo { return $this->belongsTo(KpiEvaluation::class, 'evaluation_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
