<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmPost extends Model
{
    protected $fillable = ['caption', 'media', 'created_by', 'archived_at'];

    protected $casts = ['media' => 'array', 'archived_at' => 'datetime'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
