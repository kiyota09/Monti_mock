<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmOpportunityHistory extends Model
{
    protected $fillable = [
        'opportunity_id', 'from_stage', 'to_stage', 'changed_by', 'notes',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
