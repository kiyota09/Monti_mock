<?php

namespace App\Models\Ceo;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class ExecutiveGoal extends Model
{
    protected $fillable = [
        'department', 'metric_key', 'metric_label', 'unit', 'target',
        'lower_is_better', 'period', 'notes', 'created_by',
    ];

    protected $casts = [
        'target' => 'decimal:2',
        'lower_is_better' => 'boolean',
        'period' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
