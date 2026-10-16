<?php

namespace App\Models\Logistics;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = ['user_id', 'license_number', 'license_image', 'medical_certificate', 'rating', 'is_available'];

    protected $casts = [
        'rating' => 'decimal:1',
        'is_available' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }
}