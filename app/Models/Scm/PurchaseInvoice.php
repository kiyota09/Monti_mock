<?php

namespace App\Models\Scm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_invoices';

    protected $fillable = [
        'invoice_number', 'po_id', 'po_number', 'supplier_id', 'supplier_name',
        'invoice_date', 'due_date', 'amount', 'payment_terms', 'received_at', 'status',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'received_at' => 'date',
        'amount' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(ScmPurchaseOrder::class, 'po_id');
    }

    public function payments()
    {
        return $this->hasMany(ProcurementPayment::class, 'invoice_id');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'unpaid' && $this->due_date?->isPast();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
