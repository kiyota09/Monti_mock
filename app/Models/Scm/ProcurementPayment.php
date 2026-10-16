<?php

namespace App\Models\Scm;

use Illuminate\Database\Eloquent\Model;

class ProcurementPayment extends Model
{
    protected $fillable = [
        'payment_number',
        'invoice_id',
        'invoice_number',
        'supplier_name',
        'paid_date',
        'amount',
        'method',
        'bank_reference',
        'remarks',
        'status',
    ];

    protected $casts = [
        'paid_date' => 'date',
        'amount'    => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'invoice_id');
    }
}