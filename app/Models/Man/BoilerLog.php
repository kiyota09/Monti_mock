<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class BoilerLog extends Model
{
    protected $fillable = [
        'code', 'machine_id', 'steam_pressure', 'water_level',
        'fuel_used', 'fuel_unit', 'blowdown_done', 'chemical_dosing',
        'operating_hours', 'remarks', 'operator_id', 'shift', 'processed_at',
    ];

    protected $casts = [
        'steam_pressure' => 'decimal:2',
        'fuel_used' => 'decimal:2',
        'operating_hours' => 'decimal:2',
        'blowdown_done' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
