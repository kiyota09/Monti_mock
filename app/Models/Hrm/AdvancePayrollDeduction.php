<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvancePayrollDeduction extends Model
{
    protected $table = 'hrm_advance_payroll_deductions';

    protected $fillable = ['advance_id', 'payroll_id', 'deduction_amount', 'status'];

    protected $casts = ['deduction_amount' => 'decimal:2'];

    public function advance(): BelongsTo
    {
        return $this->belongsTo(AdvancePayment::class, 'advance_id');
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }
}
