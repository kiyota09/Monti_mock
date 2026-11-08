<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use App\Models\Eco\ConversationMessage;
use App\Models\Eco\Inquiry;
use App\Models\Inv\Product;
use App\Models\Man\BomRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One round of the CRM ↔ dyeing-lab ↔ client fabric sample loop.
 *
 * Lifecycle: requested → in_progress → formulated → forwarded
 * → approved | adjustment_requested (a follow-up round points back here
 * via parent_id). Cancelled and discarded are terminal. A discarded recipe
 * request no longer blocks CRM from submitting the same client colorway.
 */
class FabricSampleRequest extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_AWAITING_CRM_REVIEW = 'awaiting_crm_review';
    public const STATUS_CRM_ADJUSTMENT_REVIEW = 'crm_adjustment_review';
    public const STATUS_FORMULATED = 'formulated';
    public const STATUS_FORWARDED = 'forwarded';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ADJUSTMENT_REQUESTED = 'adjustment_requested';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DISCARDED = 'discarded';

    protected $fillable = [
        'code',
        'inquiry_id',
        'client_id',
        'product_id',
        'fabric_name',
        'texture',
        'gsm',
        'color_hex',
        'color_image_path',
        'request_method',
        'carrier_name',
        'client_reference_path',
        'color_description',
        'notes',
        'adjustment_notes',
        'urgency',
        'status',
        'formula',
        'pantone_formula',
        'pantone_colors',
        'pantone_reference_id',
        'sample_image_path',
        'recipe_id',
        'requested_by',
        'formulated_by',
        'parent_id',
        'delivery_courier',
        'delivery_tracking_reference',
        'delivery_group_code',
        'delivery_notes',
        'delivery_sent_at',
        'client_noted_at',
        'client_received_at',
        'delivery_message_id',
    ];

    protected $casts = [
        'gsm' => 'integer',
        'formula' => 'array',
        'pantone_formula' => 'array',
        'pantone_colors' => 'array',
        'delivery_sent_at' => 'datetime',
        'client_noted_at' => 'datetime',
        'client_received_at' => 'datetime',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(BomRecord::class, 'recipe_id');
    }

    public function pantoneReference(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Man\PantoneReference::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function formulator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formulated_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->latest();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'sample_request_id')->oldest();
    }

    public function formulations(): HasMany
    {
        return $this->hasMany(FabricSampleFormulation::class, 'fabric_sample_request_id')->orderByDesc('version');
    }

    public function fabricDesignForSample(): string
    {
        $name = trim((string) $this->fabric_name);
        $parts = preg_split('/\s*(?:\x{00B7}|—)\s*/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts && preg_match('/^Yarn:\s*/i', $parts[0])) {
            array_shift($parts);
        }

        $lastPart = mb_strtolower(trim((string) end($parts)));
        if (in_array($lastPart, ['white', 'light colors', 'dark colors'], true)) {
            array_pop($parts);
        }

        return implode(' · ', array_map('trim', $parts)) ?: $name;
    }

    public function yarnTypeForSample(): string
    {
        $formula = $this->formula ?: ($this->pantone_formula ?: []);

        $yarnType = collect(data_get($formula, 'yarns', []))
            ->pluck('name')
            ->filter()
            ->unique()
            ->join(' + ');

        if ($yarnType !== '') {
            return $yarnType;
        }

        return preg_match('/^Yarn:\s*([^\x{00B7}]+)/u', trim((string) $this->fabric_name), $matches)
            ? trim($matches[1])
            : '';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_REQUESTED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_FORMULATED,
            self::STATUS_AWAITING_CRM_REVIEW,
            self::STATUS_CRM_ADJUSTMENT_REVIEW,
            self::STATUS_FORWARDED,
            self::STATUS_ADJUSTMENT_REQUESTED,
        ], true);
    }
}
