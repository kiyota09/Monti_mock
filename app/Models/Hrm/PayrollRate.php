<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;

class PayrollRate extends Model
{
    protected $table = 'payroll_rates';

    protected $fillable = [
        'daily_rate', 'daily_rate_usd', 'overtime_rate', 'night_diff_rate',
        'holiday_rate', 'special_holiday_rate', 'rest_day_rate',
        'late_deduction_per_minute', 'sss_rate', 'philhealth_rate', 'pagibig_rate'
    ];
}