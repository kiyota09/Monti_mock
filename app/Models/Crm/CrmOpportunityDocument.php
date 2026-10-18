<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file or image uploaded from a pipeline card (only on stages with
 * `allows_uploads`). Belongs to the deal it was uploaded from and to the
 * client on that deal.
 */
class CrmOpportunityDocument extends Model
{
    protected $fillable = [
        'opportunity_id', 'client_id', 'stage_id',
        'file_path', 'original_name', 'mime', 'size', 'uploaded_by',
    ];

    protected $casts = ['size' => 'integer'];

    protected $appends = ['file_url', 'is_image'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        // Root-relative path (same convention as logos / feedback uploads).
        return $this->file_path ? '/storage/' . ltrim($this->file_path, '/') : null;
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/')
            || in_array(strtolower(pathinfo((string) $this->original_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
    }
}
