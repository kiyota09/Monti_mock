<?php

namespace App\Models\Fin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinBill extends Model
{
    protected $fillable = [
        'bill_no', 'supplier_id', 'purchase_invoice_id', 'supplier_name', 'category',
        'amount', 'due_date', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(FinBillPayment::class);
    }

    public function paidTotal(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function balance(): float
    {
        return round((float) $this->amount - $this->paidTotal(), 2);
    }

    public function refreshStatus(): void
    {
        $balance = $this->balance();
        $paid = $this->paidTotal();
        $this->status = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        $this->save();
    }

    public function displayStatus(): string
    {
        if ($this->balance() > 0 && $this->due_date && $this->due_date->isPast()) {
            return 'overdue';
        }

        return $this->status;
    }
}
