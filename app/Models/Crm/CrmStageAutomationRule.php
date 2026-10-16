<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Placeholder for future per-stage automations.
 * Rows are creatable but never executed yet (UI shows "coming soon").
 */
class CrmStageAutomationRule extends Model
{
    public const TRIGGERS = ['on_enter', 'on_exit', 'on_overdue'];
    public const ACTIONS = ['create_activity', 'notify', 'set_field'];

    protected $fillable = ['stage_id', 'trigger', 'action', 'payload', 'is_active'];

    protected $casts = [
        'payload' => 'array',
        'is_active' => 'boolean',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }
}
