<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use App\Support\ClientPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmContact extends Model
{
    protected $fillable = [
        'client_id', 'lead_id', 'name', 'organization', 'title', 'email', 'phone',
        'is_decision_maker', 'is_primary', 'notes',
        'status', 'has_account', 'password', 'access_pages', 'origin', 'account_created_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_decision_maker' => 'boolean',
        'is_primary' => 'boolean',
        'has_account' => 'boolean',
        'access_pages' => 'array',
        'account_created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The primary contact ("main account") of a business partner always
        // holds full access to every page of that account's partner portal.
        // Enforcing it here keeps the primary designation and the granted pages
        // aligned no matter where a primary is set (CRM contacts, won deals, …).
        static::saving(function (CrmContact $contact) {
            if ($contact->is_primary && $contact->client_id) {
                $contact->access_pages = ClientPortal::keys();
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class);
    }
}
