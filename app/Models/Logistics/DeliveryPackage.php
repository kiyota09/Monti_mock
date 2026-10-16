<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DeliveryPackage extends Pivot
{
    protected $table = 'delivery_packages';
}