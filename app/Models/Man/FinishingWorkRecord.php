<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class FinishingWorkRecord extends Model
{
    protected $fillable = [
        'finishing_work_item_id','stage','attempt','operator_id','temperature_c','gsm','shrinkage_percent',
        'chemicals_used','remarks','result','reviewed_by','reviewed_at',
    ];

    protected $casts = ['temperature_c'=>'decimal:2','gsm'=>'decimal:2','shrinkage_percent'=>'decimal:3','reviewed_at'=>'datetime'];
    public function item() { return $this->belongsTo(FinishingWorkItem::class,'finishing_work_item_id'); }
    public function operator() { return $this->belongsTo(User::class,'operator_id'); }
    public function reviewer() { return $this->belongsTo(User::class,'reviewed_by'); }
}
