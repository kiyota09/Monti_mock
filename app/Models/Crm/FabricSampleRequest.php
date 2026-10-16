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
 * via parent_id). Cancelled is terminal.
 */
class FabricSampleRequest extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FORMULATED = 'formulated';
    public const STATUS_FORWARDED = 'forwarded';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ADJUSTMENT_REQUESTED = 'adjustment_requested';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code',
        'inquiry_id',
        'client_id',
        'product_id',
        'fabric_name',
        'color_description',
        'notes',
        'adjustment_notes',
        'urgency',
        'status',
        'formula',
        'sample_image_path',
        'recipe_id',
        'requested_by',
        'formulated_by',
        'parent_id',
    ];

    protected $casts = [
        'formula' => 'array',
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

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_REQUESTED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_FORMULATED,
            self::STATUS_FORWARDED,
            self::STATUS_ADJUSTMENT_REQUESTED,
        ], true);
    }
}
