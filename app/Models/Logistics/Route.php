<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;
use App\Models\Crm\Client;

class Route extends Model
{
    protected $fillable = [
        'name',
        'client_id',
        'origin',
        'origin_lat',
        'origin_lng',
        'destination',
        'destination_lat',
        'destination_lng',
        'distance_km',
        'estimated_minutes',
        'waypoints',
        'route_geometry',
    ];

    protected $casts = [
        'waypoints'       => 'array',
        'route_geometry'  => 'array',
        'distance_km'     => 'float',
        'origin_lat'      => 'float',
        'origin_lng'      => 'float',
        'destination_lat' => 'float',
        'destination_lng' => 'float',
    ];

    /**
     * The business client this delivery route serves.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}