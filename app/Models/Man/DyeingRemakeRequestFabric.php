<?php

namespace App\Models\Man;

use Illuminate\Database\Eloquent\Model;

class DyeingRemakeRequestFabric extends Model
{
    protected $fillable = ['dyeing_remake_request_id', 'original_fabric_id', 'replacement_fabric_id'];

    public function request() { return $this->belongsTo(DyeingRemakeRequest::class, 'dyeing_remake_request_id'); }
    public function originalFabric() { return $this->belongsTo(Fabric::class, 'original_fabric_id'); }
    public function replacementFabric() { return $this->belongsTo(Fabric::class, 'replacement_fabric_id'); }
}
