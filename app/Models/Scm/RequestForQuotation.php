<?php

namespace App\Models\Scm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestForQuotation extends Model
{
    use SoftDeletes;

    protected $table = 'request_for_quotations';

    protected $fillable = [
        'rfq_number', 
        'mr_ref', 
        'mr_id', 
        'material_id', 
        'material_name',
        'category', 
        'unit', 
        'required_qty', 
        'deadline', 
        'sent_at',
        'delivery_address', 
        'payment_terms', 
        'notes', 
        'supplier_ids', 
        'status',
    ];

    protected $casts = [
        'deadline' => 'date',
        'sent_at' => 'date',
        'supplier_ids' => 'array',
        'required_qty' => 'decimal:2',
    ];

    public function responses()
    {
        return $this->hasMany(RfqResponse::class, 'rfq_id');
    }

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class, 'mr_id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(ScmPurchaseOrder::class, 'rfq_id');
    }
}
