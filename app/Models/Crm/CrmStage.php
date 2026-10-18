<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmStage extends Model
{
    protected $fillable = [
        'name', 'sequence', 'is_folded', 'is_won', 'is_lost', 'allows_uploads', 'default_probability', 'created_by',
    ];

    protected $casts = [
        'is_folded' => 'boolean',
        'is_won' => 'boolean',
        'is_lost' => 'boolean',
        'allows_uploads' => 'boolean',
        'default_probability' => 'integer',
        'sequence' => 'integer',
    ];

    /** A terminal column: won or lost. Deals here stop accepting new activities. */
    public function isTerminal(): bool
    {
        return (bool) $this->is_won || (bool) $this->is_lost;
    }

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
