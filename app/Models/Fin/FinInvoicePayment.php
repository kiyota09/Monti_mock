<?php

namespace App\Models\Fin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinInvoicePayment extends Model
{
    protected $fillable = [
        'fin_invoice_id', 'amount', 'paid_at', 'method', 'reference', 'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinInvoice::class, 'fin_invoice_id');
    }
}
