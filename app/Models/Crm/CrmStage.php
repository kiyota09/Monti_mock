<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmStage extends Model
{
    protected $fillable = [
        'name', 'sequence', 'is_folded', 'is_won', 'default_probability', 'created_by',
    ];

    protected $casts = [
        'is_folded' => 'boolean',
        'is_won' => 'boolean',
        'default_probability' => 'integer',
        'sequence' => 'integer',
    ];

    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'stage_id')->orderByDesc('updated_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function automationRules(): HasMany
    {
        return $this->hasMany(CrmStageAutomationRule::class, 'stage_id');
    }
}
