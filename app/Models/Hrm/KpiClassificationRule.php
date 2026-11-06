<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;

class KpiClassificationRule extends Model
{
    protected $table = 'hrm_kpi_classification_rules';
    protected $guarded = ['id'];
    protected function casts(): array { return ['active' => 'boolean', 'minimum_score' => 'decimal:2', 'maximum_score' => 'decimal:2']; }
}
