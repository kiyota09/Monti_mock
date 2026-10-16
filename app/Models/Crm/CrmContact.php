<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmContact extends Model
{
    protected $fillable = [
        'client_id', 'lead_id', 'name', 'organization', 'title', 'email', 'phone',
        'is_decision_maker', 'is_primary', 'notes',
    ];

    protected $casts = [
        'is_decision_maker' => 'boolean',
        'is_primary' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class);
    }
}
