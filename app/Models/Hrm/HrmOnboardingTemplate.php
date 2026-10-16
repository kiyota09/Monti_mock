<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmOnboardingTemplate extends Model
{
    protected $table = 'hrm_onboarding_templates';
    protected $fillable = [
        'code', 'name', 'description', 'department_id', 'position_id',
        'employment_type_id', 'work_mode', 'status', 'version', 'is_default', 'created_by',
    ];
    protected $casts = ['is_default' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(HrmOnboardingTemplateItem::class, 'template_id')->orderBy('sort_order');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(HrmOnboardingTemplateActivity::class, 'template_id')->orderBy('sort_order');
    }
}
