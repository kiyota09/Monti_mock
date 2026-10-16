<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CrmCampaign extends Model
{
    protected $fillable = [
        'name', 'channel', 'audience', 'cost', 'starts_at', 'ends_at', 'status', 'created_by',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(CrmLead::class, 'crm_campaign_leads', 'campaign_id', 'lead_id')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
