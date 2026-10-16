<?php

namespace App\Models\It;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItSystemCheck extends Model
{
    protected $table = 'it_system_checks';

    public $timestamps = false;

    protected $fillable = [
        'system_id',
        'status',
        'checked_by',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(ItSystem::class, 'system_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
