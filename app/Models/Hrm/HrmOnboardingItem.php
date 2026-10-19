<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HrmOnboardingItem extends Model
{
    protected $table = 'hrm_onboarding_items';
    protected $fillable = [
        'onboarding_id', 'template_item_id', 'name', 'status', 'due_date',
        'file_url', 'original_name', 'value', 'rejection_reason', 'submitted_at',
        'attachment_path', 'attachment_name',
    ];
    protected $casts = ['due_date' => 'date', 'submitted_at' => 'datetime'];
    protected $appends = ['attachment_url'];

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null;
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(HrmOnboarding::class, 'onboarding_id');
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(HrmOnboardingTemplateItem::class, 'template_item_id');
    }
}
