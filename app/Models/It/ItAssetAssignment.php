<?php

namespace App\Models\It;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItAssetAssignment extends Model
{
    protected $table = 'it_asset_assignments';

    public $timestamps = false;

    protected $fillable = [
        'asset_id',
        'user_id',
        'assigned_at',
        'returned_at',
        'assigned_by',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
