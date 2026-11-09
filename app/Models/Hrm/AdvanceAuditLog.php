<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceAuditLog extends Model
{
    protected $table = 'hrm_advance_audit_logs';

    public $timestamps = false;

    protected $fillable = ['advance_id', 'actor_id', 'action', 'from_status', 'to_status', 'remarks', 'details', 'created_at'];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
