<?php

namespace App\Models\Ceo;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class ExecutiveActionLog extends Model
{
    protected $fillable = [
        'actor_id', 'action_type', 'subject_label', 'subject_id',
        'decision', 'reason',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
