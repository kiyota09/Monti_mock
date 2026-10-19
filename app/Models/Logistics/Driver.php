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

    public function dutyLogs()
    {
        return $this->hasMany(DriverDutyLog::class)->latest('started_at');
    }

    public function inspections()
    {
        return $this->hasMany(DriverVehicleInspection::class)->latest('inspected_at');
    }

    public function incidents()
    {
        return $this->hasMany(DriverIncident::class)->latest('reported_at');
    }

    public function expenses()
    {
        return $this->hasMany(DriverExpense::class)->latest('spent_at');
    }

    public function documents()
    {
        return $this->hasMany(DriverDocument::class)->latest();
    }
}