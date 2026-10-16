<?php

namespace App\Models\Scm;

use App\Models\Pro\Supplier;
use Illuminate\Database\Eloquent\Model;

class RfqResponse extends Model
{
    protected $table = 'rfq_responses';

    protected $fillable = [
        'rfq_id', 'supplier_id', 'supplier_name', 'unit_price', 'total_price',
        'lead_time', 'validity_date', 'payment_terms', 'notes',
        'decline_reason', 'submitted_at', 'status',
    ];

    protected $casts = [
        'validity_date' => 'date',
        'submitted_at' => 'date',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function rfq()
    {
        return $this->belongsTo(RequestForQuotation::class, 'rfq_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
