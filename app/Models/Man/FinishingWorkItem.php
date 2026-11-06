<?php

namespace App\Models\Man;

use Illuminate\Database\Eloquent\Model;

class FinishingWorkItem extends Model
{
    protected $fillable = ['finishing_work_order_id','fabric_id','status'];

    public function workOrder() { return $this->belongsTo(FinishingWorkOrder::class,'finishing_work_order_id'); }
    public function fabric() { return $this->belongsTo(Fabric::class); }
    public function records() { return $this->hasMany(FinishingWorkRecord::class)->orderBy('attempt'); }
    public function packageGroupItem() { return $this->hasOne(FinishingPackageGroupItem::class); }
}
