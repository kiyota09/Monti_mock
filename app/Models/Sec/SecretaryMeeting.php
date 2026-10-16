<?php

namespace App\Models\Sec;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class SecretaryMeeting extends Model
{
    protected $fillable = [
        'title', 'meeting_date', 'start_time', 'end_time', 'venue',
        'organizer', 'attendees', 'agenda', 'status', 'minutes', 'created_by',
    ];

    protected $casts = [
        'meeting_date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
