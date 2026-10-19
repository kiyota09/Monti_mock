<?php

namespace App\Models\Logistics;

use App\Models\Crm\Client;
use Illuminate\Database\Eloquent\Model;

class ConductorReturn extends Model
{
    protected $fillable = [
        'conductor_id',
        'delivery_id',
        'delivery_stop_id',
        'client_id',
        'package_number',
        'product_name',
        'quantity',
        'reason',
        'action',
        'photo_path',
        'notes',
        'status',
        'reported_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reported_at' => 'datetime',
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
