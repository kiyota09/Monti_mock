<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class LeadInterview extends Model
{
    protected $fillable = ['lead_id', 'user_id', 'scheduled_at', 'location', 'notes'];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(CrmLead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
