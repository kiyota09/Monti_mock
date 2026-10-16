<?php

namespace App\Models\Scm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Pro\Supplier;
use App\Models\Scm\ScmPurchaseOrderItem;
use App\Models\Scm\RequestForQuotation;
use App\Models\Scm\PurchaseInvoice;
use App\Models\Core\User;

class ScmPurchaseOrder extends Model
{
    use SoftDeletes;

    protected $table = 'scm_purchase_orders';

    protected $fillable = [
        'po_number', 'supplier_id', 'supplier_name', 'rfq_ref', 'rfq_id',
        'issued_date', 'expected_delivery', 'subtotal', 'tax_rate',
        'tax_amount', 'grand_total', 'notes', 'received', 'status',
        'finance_status', 'finance_decided_by', 'finance_decided_at', 'finance_remarks',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'received' => 'boolean',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items()
    {
        return $this->hasMany(ScmPurchaseOrderItem::class, 'scm_purchase_order_id');
    }

    public function rfq()
    {
        return $this->belongsTo(RequestForQuotation::class, 'rfq_id');
    }

    public function invoices()
    {
        return $this->hasMany(PurchaseInvoice::class, 'po_id');
    }
}