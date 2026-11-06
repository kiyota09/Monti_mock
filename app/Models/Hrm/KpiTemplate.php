<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiTemplate extends Model
{
    protected $table = 'hrm_kpi_templates';
    protected $guarded = ['id'];

    protected function casts(): array { return ['rating_scale' => 'array', 'effective_date' => 'date']; }

    public function department(): BelongsTo { return $this->belongsTo(HrmDepartment::class, 'department_id'); }
    public function position(): BelongsTo { return $this->belongsTo(HrmPosition::class, 'position_id'); }
    public function criteria(): HasMany { return $this->hasMany(KpiTemplateCriterion::class, 'template_id')->orderBy('sort_order'); }
}
