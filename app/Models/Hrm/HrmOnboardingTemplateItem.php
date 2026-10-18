<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HrmOnboardingTemplateItem extends Model
{
    protected $table = 'hrm_onboarding_template_items';
    protected $fillable = [
        'template_id', 'category', 'name', 'description', 'input_type', 'options', 'responsible_party',
        'is_required', 'applicant_visible', 'is_active', 'due_rule_type', 'due_rule_value', 'sort_order',
        'attachment_path', 'attachment_name',
    ];
    protected $casts = ['is_required' => 'boolean', 'applicant_visible' => 'boolean', 'is_active' => 'boolean', 'options' => 'array'];
    protected $appends = ['attachment_url'];

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null;
    }
}
