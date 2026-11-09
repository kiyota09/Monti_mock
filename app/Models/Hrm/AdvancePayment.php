<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvancePayment extends Model
{
    protected $table = 'hrm_advance_payments';

    protected $fillable = [
        'advance_number', 'user_id', 'employee_id', 'employee_name', 'department', 'position',
        'advance_amount', 'reason', 'request_date', 'planned_release_date', 'payment_method',
        'repayment_method', 'installments', 'installment_amount', 'remarks',
        'payroll_deduction_authorized', 'authorization_document_path', 'authorization_document_name',
        'status', 'total_repaid', 'outstanding_balance', 'created_by', 'released_by',
        'payment_reference', 'actual_release_date', 'cancelled_by', 'cancelled_at',
    ];

    protected $casts = [
        'advance_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'total_repaid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'payroll_deduction_authorized' => 'boolean',
        'request_date' => 'date',
        'planned_release_date' => 'date',
        'actual_release_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AdvanceApproval::class, 'advance_id')->orderBy('decided_at');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(AdvancePayrollDeduction::class, 'advance_id');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(AdvanceRepayment::class, 'advance_id')->latest('repaid_at');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AdvanceAuditLog::class, 'advance_id')->latest('created_at');
    }
}
