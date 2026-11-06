<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class FinishingPackageGroup extends Model
{
    protected $fillable = ['finishing_work_order_id','code','group_name','packed_by','status','warehouse_package_id','sent_to_logistics_at'];
    protected $casts = ['sent_to_logistics_at'=>'datetime'];
    public function workOrder() { return $this->belongsTo(FinishingWorkOrder::class,'finishing_work_order_id'); }
    public function packer() { return $this->belongsTo(User::class,'packed_by'); }
    public function warehousePackage() { return $this->belongsTo(\App\Models\War\WarehousePackage::class); }
    public function items() { return $this->hasMany(FinishingPackageGroupItem::class); }
}
