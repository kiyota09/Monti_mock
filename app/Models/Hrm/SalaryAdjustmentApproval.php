<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryAdjustmentApproval extends Model
{
    protected $fillable = [
        'salary_adjustment_id', 'decided_by', 'decision', 'remarks',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(SalaryAdjustment::class, 'salary_adjustment_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
