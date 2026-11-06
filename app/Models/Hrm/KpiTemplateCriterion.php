<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiTemplateCriterion extends Model
{
    protected $table = 'hrm_kpi_criteria';
    protected $guarded = ['id'];
    protected function casts(): array { return ['rating_guidelines' => 'array']; }
    public function template(): BelongsTo { return $this->belongsTo(KpiTemplate::class, 'template_id'); }
}
