<?php

namespace App\Models\Fin;

use Illuminate\Database\Eloquent\Model;

class FinExpense extends Model
{
    protected $fillable = [
        'expense_date', 'category', 'description', 'amount', 'department', 'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];
}
