<?php

namespace App\Models\Logistics;

use App\Models\Crm\Client;
use Illuminate\Database\Eloquent\Model;

class ConductorCollection extends Model
{
    protected $fillable = [
        'conductor_id',
        'delivery_id',
        'delivery_stop_id',
        'client_id',
        'method',
        'reference',
        'amount',
        'receipt_path',
        'remarks',
        'status',
        'collected_at',
        'remitted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'collected_at' => 'datetime',
        'remitted_at' => 'datetime',
    ];

    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function stop()
    {
        return $this->belongsTo(DeliveryStop::class, 'delivery_stop_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
