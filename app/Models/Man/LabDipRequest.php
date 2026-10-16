<?php

namespace App\Models\Man;

use App\Models\Core\User;
use App\Models\Ord\SalesOrder;
use Illuminate\Database\Eloquent\Model;

class LabDipRequest extends Model
{
    protected $fillable = [
        'code', 'sales_order_id', 'customer_ref', 'pantone_code',
        'rgb_lab_values', 'swatch_id', 'fabric_details', 'urgency',
        'status', 'remarks', 'operator_id', 'shift', 'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function trials()
    {
        return $this->hasMany(LabTrial::class, 'dip_request_id')->orderBy('trial_no');
    }

    public function tests()
    {
        return $this->hasMany(LabTest::class, 'dip_request_id')->latest();
    }
}
