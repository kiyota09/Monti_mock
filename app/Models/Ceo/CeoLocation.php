<?php

namespace App\Models\Ceo;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class CeoLocation extends Model {
    protected $fillable = ['user_id', 'latitude', 'longitude', 'range_radius', 'is_active', 'label', 'place_name'];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}