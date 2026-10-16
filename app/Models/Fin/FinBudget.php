<?php

namespace App\Models\Fin;

use Illuminate\Database\Eloquent\Model;

class FinBudget extends Model
{
    protected $fillable = [
        'department', 'period', 'allocated', 'created_by',
    ];

    protected $casts = [
        'allocated' => 'decimal:2',
    ];
}
