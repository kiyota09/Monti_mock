<?php

namespace App\Models\Client;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientQuotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'quotation_number',
        'rfq_reference',
        'issue_date',
        'valid_until',
        'status',
        'prepared_by',
        'manufacturer_info',
        'billing_address',
        'shipping_address',
        'lead_time',
        'incoterms',
        'shipping_method',
        'payment_terms',
        'subtotal',
        'shipping_cost',
        'tax',
        'grand_total',
        'currency',
        'terms_conditions',
        'custom_notes',
        'client_accepted_at',
        'client_rejected_at',
        'expired_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'valid_until' => 'date',
        'client_accepted_at' => 'datetime',
        'client_rejected_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function items()
    {
        return $this->hasMany(ClientQuotationItem::class, 'quotation_id');
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
