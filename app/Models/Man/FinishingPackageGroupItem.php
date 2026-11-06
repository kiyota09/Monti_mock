<?php

namespace App\Models\Man;

use Illuminate\Database\Eloquent\Model;

class FinishingPackageGroupItem extends Model
{
    protected $fillable = ['finishing_package_group_id','finishing_work_item_id','warehouse_package_id'];
    public function group() { return $this->belongsTo(FinishingPackageGroup::class,'finishing_package_group_id'); }
    public function workItem() { return $this->belongsTo(FinishingWorkItem::class,'finishing_work_item_id'); }
    public function warehousePackage() { return $this->belongsTo(\App\Models\War\WarehousePackage::class); }
}
