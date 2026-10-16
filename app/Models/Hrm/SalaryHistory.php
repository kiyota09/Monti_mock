<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryHistory extends Model
{
    protected $table = 'salary_history';

    protected $fillable = [
        'user_id', 'previous_salary', 'new_salary', 'effective_date',
        'reason', 'source', 'salary_adjustment_id', 'recorded_by',
    ];

    protected $casts = [
        'previous_salary' => 'float',
        'new_salary' => 'float',
        'effective_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(SalaryAdjustment::class, 'salary_adjustment_id');
    }
}
