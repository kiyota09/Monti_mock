<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class LabStockSolution extends Model
{
    protected $fillable = [
        'material_name', 'concentration', 'lot_no', 'prepared_at',
        'expiry_date', 'sds_url', 'remarks', 'prepared_by',
    ];

    protected $casts = [
        'prepared_at' => 'date',
        'expiry_date' => 'date',
    ];

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
