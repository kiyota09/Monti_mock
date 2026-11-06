<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyReferralHistory extends Model
{
    protected $table = 'hrm_agency_referral_histories';

    protected $fillable = [
        'agency_referral_id', 'action', 'from_status', 'to_status', 'notes', 'metadata', 'actor_id', 'agency_user_id',
    ];

    protected $casts = ['metadata' => 'array'];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(AgencyReferral::class, 'agency_referral_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function agencyActor(): BelongsTo
    {
        return $this->belongsTo(AgencyUser::class, 'agency_user_id');
    }
}
