<?php

namespace App\Models\Fin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinPaymongoCheckout extends Model
{
    protected $table = 'fin_paymongo_checkouts';

    protected $fillable = [
        'reference', 'fin_bill_id', 'fin_invoice_id', 'amount', 'status', 'method',
        'paymongo_session_id', 'checkout_url', 'gateway_reference',
        'payload', 'paid_at', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
        'paid_at' => 'datetime',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(FinBill::class, 'fin_bill_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinInvoice::class, 'fin_invoice_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
