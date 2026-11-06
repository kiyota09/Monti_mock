<?php

namespace App\Models\Man;

use Illuminate\Database\Eloquent\Model;

class DyeingBatchFabric extends Model
{
    protected $fillable = [
        'dyeing_batch_id', 'fabric_id', 'requires_bleaching', 'current_stage', 'status', 'replacement_fabric_id',
    ];

    protected $casts = ['requires_bleaching' => 'boolean'];

    public function batch() { return $this->belongsTo(DyeingBatch::class, 'dyeing_batch_id'); }
    public function fabric() { return $this->belongsTo(Fabric::class); }
    public function replacementFabric() { return $this->belongsTo(Fabric::class, 'replacement_fabric_id'); }
    public function records() { return $this->hasMany(DyeingStageRecord::class)->orderBy('attempt'); }
    public function latestRecord() { return $this->hasOne(DyeingStageRecord::class)->latestOfMany('attempt'); }
}
