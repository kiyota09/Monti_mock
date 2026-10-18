<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared team log for the Opportunities board — timestamped notes documenting
 * what is happening and progressing across the pipeline.
 */
class CrmPipelineNote extends Model
{
    protected $fillable = [
        'user_id', 'body',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
