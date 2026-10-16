<?php

namespace App\Models\Vp;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class ExecutiveDirective extends Model
{
    protected $fillable = [
        'title', 'body', 'assigned_to', 'due_date', 'priority',
        'status', 'completion_notes', 'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
