<?php

namespace App\Models\Man;

use Illuminate\Database\Eloquent\Model;

class PackageItem extends Model
{
    protected $fillable = [
        'package_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
