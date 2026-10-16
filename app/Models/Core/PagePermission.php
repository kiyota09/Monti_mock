<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagePermission extends Model
{
    use HasFactory;

    protected $table = 'page_permissions';

    protected $fillable = [
        'user_id',
        'module',
        'page',
        'permission_level',   // ← ADD THIS
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
