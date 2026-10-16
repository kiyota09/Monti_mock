<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompensationComponent extends Model
{
    protected $fillable = [
        'user_id', 'name', 'amount', 'frequency', 'is_active', 'notes',
    ];

    protected $casts = [
        'amount' => 'float',
        'is_active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
