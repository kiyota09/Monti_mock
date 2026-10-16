<?php

namespace App\Models\Fin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinBillPayment extends Model
{
    protected $fillable = [
        'fin_bill_id', 'amount', 'paid_at', 'method', 'reference', 'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(FinBill::class, 'fin_bill_id');
    }
}
