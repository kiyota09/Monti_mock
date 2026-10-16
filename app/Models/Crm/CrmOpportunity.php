<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmOpportunity extends Model
{
    public const STAGES = [
        'qualification' => 10,
        'sampling' => 25,
        'quotation' => 50,
        'negotiation' => 75,
        'won' => 100,
        'lost' => 0,
    ];

    public const TERMINAL = ['won', 'lost'];

    protected $fillable = [
        'client_id', 'lead_id', 'contact_id', 'stage_id', 'title', 'stage', 'value',
        'probability', 'priority', 'expected_close', 'owner_id', 'lost_reason',
        'internal_notes', 'source', 'medium', 'campaign', 'referred_by', 'email', 'phone',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'expected_close' => 'date',
        'priority' => 'integer',
    ];

    public function stageRef(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CrmOpportunityHistory::class, 'opportunity_id')->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'opportunity_id')->latest();
    }

    /** Activity state for the pipeline card icon / stage bar. */
    public function getActivityStateAttribute(): string
    {
        $next = $this->relationLoaded('activities')
            ? $this->activities->where('status', 'planned')->sortBy('due_date')->first()
            : $this->activities()->where('status', 'planned')->orderByRaw('due_date IS NULL, due_date ASC')->first();

        if (! $next || ! $next->due_date) {
            return $next ? 'planned' : 'none';
        }
        $due = $next->due_date->startOfDay();
        $today = now()->startOfDay();
        if ($due->lt($today)) {
            return 'overdue';
        }
        if ($due->eq($today)) {
            return 'today';
        }

        return 'planned';
    }

    public function getWeightedAttribute(): float
    {
        return round(((float) $this->value) * ((int) $this->probability) / 100, 2);
    }

    public static function allowedMoves(string $from): array
    {
        if (in_array($from, self::TERMINAL, true)) {
            return [];
        }
        $order = ['qualification', 'sampling', 'quotation', 'negotiation'];

        return array_merge(
            array_values(array_diff($order, [$from])),
            self::TERMINAL
        );
    }
}
