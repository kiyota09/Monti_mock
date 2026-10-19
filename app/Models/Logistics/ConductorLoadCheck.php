<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class ConductorLoadCheck extends Model
{
    protected $fillable = [
        'conductor_id',
        'delivery_id',
        'type',
        'checklist',
        'discrepancies',
        'severity',
        'photo_path',
        'passed',
        'checked_at',
    ];

    protected $casts = [
        'checklist' => 'array',
        'passed' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }
}
