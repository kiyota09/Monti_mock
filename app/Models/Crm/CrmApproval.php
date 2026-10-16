<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class CrmApproval extends Model
{
    protected $fillable = ['user_id', 'action', 'data', 'status', 'manager_id', 'reviewed_at'];

    protected $casts = [
        'data' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
