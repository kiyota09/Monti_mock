<?php

namespace App\Models\Eco;

use App\Models\Eco\Inquiry;
use Illuminate\Database\Eloquent\Model;

class EcoQuotation extends Model
{
    protected $table = 'eco_quotations';

    protected $fillable = [
        'client_id',
        'inquiry_id',
        'quotation_number',
        'vat_type',
        'payment_terms',
        'notes',
        'grand_total',
        'status',
        'reject_reason',
        'request_new_quote',
    ];

    protected $casts = [
        'grand_total'      => 'decimal:2',
        'request_new_quote' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function items()
    {
        return $this->hasMany(EcoQuotationItem::class, 'eco_quotation_id');
    }

    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class, 'inquiry_id');
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Crm\Client::class, 'client_id');
    }
}