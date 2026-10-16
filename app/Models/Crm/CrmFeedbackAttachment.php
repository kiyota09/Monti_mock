<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmFeedbackAttachment extends Model
{
    protected $fillable = [
        'feedback_id', 'file_path', 'original_name', 'mime', 'size', 'uploaded_by',
    ];

    protected $casts = ['size' => 'integer'];

    protected $appends = ['file_url', 'is_image'];

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(CrmFeedback::class, 'feedback_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        // Root-relative path (same convention as logos: `/storage/...`).
        // Storage::url() returns an absolute URL bound to APP_URL
        // (http://localhost), which breaks under 127.0.0.1 / serve ports.
        return $this->file_path ? '/storage/' . ltrim($this->file_path, '/') : null;
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/')
            || in_array(strtolower(pathinfo((string) $this->original_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
    }
}
