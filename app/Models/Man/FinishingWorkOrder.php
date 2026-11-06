<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class FinishingWorkOrder extends Model
{
    protected $fillable = [
        'manufacturing_order_id','sales_order_id','extraction_method','extraction_assigned_to',
        'drying_method','drying_assigned_to','status','quality_checked_by','quality_checked_at','quality_remarks',
    ];

    protected $casts = ['quality_checked_at'=>'datetime'];

    public function manufacturingOrder() { return $this->belongsTo(ManufacturingOrder::class); }
    public function salesOrder() { return $this->belongsTo(\App\Models\Ord\SalesOrder::class); }
    public function extractionOperator() { return $this->belongsTo(User::class,'extraction_assigned_to'); }
    public function dryingOperator() { return $this->belongsTo(User::class,'drying_assigned_to'); }
    public function items() { return $this->hasMany(FinishingWorkItem::class); }
    public function packageGroups() { return $this->hasMany(FinishingPackageGroup::class); }
    public function qualityChecker() { return $this->belongsTo(User::class,'quality_checked_by'); }
}
