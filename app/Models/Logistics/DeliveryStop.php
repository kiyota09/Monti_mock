<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;
use App\Models\Crm\Client;

class DeliveryStop extends Model
{
    protected $fillable = [
        'delivery_id',
        'client_id',
        'sequence',
        'label',
        'address',
        'latitude',
        'longitude',
        'status',
        'arrived_at',
        'notes',
        'outcome',
        'signature_path',
        'pod_photo_path',
        'pod_notes',
        'recipient_name',
        'completed_at',
    ];

    protected $casts = [
        'sequence'   => 'integer',
        'latitude'   => 'float',
        'longitude'  => 'float',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
