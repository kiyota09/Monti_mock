<?php

namespace App\Models\Ceo;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CeoPlanner extends Model
{
    use HasFactory;

    protected $table = 'ceo_planner';

    protected $fillable = [
        'user_id', 'event_date', 'title', 'start_time', 'end_time',
        'location', 'attendee', 'notes'
    ];

    protected $casts = [
        'event_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}