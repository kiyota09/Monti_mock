<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single entry in a lead's investigation log (added before the lead is
 * pushed to the pipeline). Entries are free-form notes, flags that call out
 * something noteworthy, or uploaded files/images used as evidence.
 */
class CrmLeadInvestigation extends Model
{
    protected $fillable = [
        'lead_id', 'user_id', 'type', 'note',
        'file_path', 'original_name', 'mime', 'size',
    ];

    protected $casts = ['size' => 'integer'];

    protected $appends = ['file_url', 'is_image'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        // Root-relative path (same convention as logos / card documents).
        return $this->file_path ? '/storage/' . ltrim($this->file_path, '/') : null;
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/')
            || in_array(strtolower(pathinfo((string) $this->original_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
    }
}
