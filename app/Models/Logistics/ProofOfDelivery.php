<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class ProofOfDelivery extends Model
{
    protected $fillable = ['delivery_id', 'image_path', 'notes', 'delivered_at'];

    protected $casts = ['delivered_at' => 'datetime'];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }
}