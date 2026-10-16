<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class ShiftHandover extends Model
{
    protected $fillable = [
        'department', 'shift_date', 'shift_type', 'unfinished_work',
        'machine_notes', 'hot_jobs', 'safety_notes', 'author_id',
    ];

    protected $casts = [
        'shift_date' => 'date',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
