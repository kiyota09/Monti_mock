<?php

namespace App\Models;

use App\Models\Pro\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierMessage extends Model
{
    protected $fillable = [
        'supplier_id',
        'sender_type',
        'sender_id',
        'message',
        'attachment',
        'meeting_data',
        'is_system_event',
    ];

    protected $casts = [
        'meeting_data' => 'array',
        'is_system_event' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
