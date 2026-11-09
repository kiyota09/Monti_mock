<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceApproval extends Model
{
    protected $table = 'hrm_advance_approvals';

    protected $fillable = ['advance_id', 'stage', 'decision', 'approver_id', 'decided_at', 'remarks'];

    protected $casts = ['decided_at' => 'datetime'];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
