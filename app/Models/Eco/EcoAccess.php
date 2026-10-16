<?php

namespace App\Models\Eco;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class EcoAccess extends Model
{
    protected $table = 'eco_access';
    protected $fillable = ['user_id', 'can_access_eco'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}