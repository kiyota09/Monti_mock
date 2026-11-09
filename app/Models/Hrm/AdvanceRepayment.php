<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceRepayment extends Model
{
    protected $table = 'hrm_advance_repayments';

    protected $fillable = ['advance_id', 'payroll_id', 'payroll_period', 'amount', 'repaid_at'];

    protected $casts = ['amount' => 'decimal:2', 'repaid_at' => 'datetime'];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }
}
