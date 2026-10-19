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

    /**
     * Columns in board order: open stages by probability, then Won, then Lost.
     * Mirrors orderStages() on the frontend so move validation agrees with UI.
     */
    public static function pipelineOrder(): \Illuminate\Support\Collection
    {
        return static::query()
            ->orderByRaw('CASE WHEN is_won = 1 THEN 1 WHEN is_lost = 1 THEN 2 ELSE 0 END')
            ->orderBy('default_probability')
            ->orderBy('sequence')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
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
