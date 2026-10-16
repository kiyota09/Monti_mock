<?php

namespace App\Models\Logistics;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class Conductor extends Model
{
    protected $fillable = ['user_id', 'is_available'];

    protected $casts = ['is_available' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deliveriesAsFirst()
    {
        return $this->hasMany(Delivery::class, 'conductor1_id');
    }

    public function deliveriesAsSecond()
    {
        return $this->hasMany(Delivery::class, 'conductor2_id');
    }

    public function reports()
    {
        return $this->hasMany(ConductorReport::class);
    }
}