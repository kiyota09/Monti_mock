<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class DriverExpense extends Model
{
    protected $fillable = [
        'driver_id',
        'delivery_id',
        'type',
        'amount',
        'receipt_path',
        'notes',
        'spent_at',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_at' => 'date',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }
}
