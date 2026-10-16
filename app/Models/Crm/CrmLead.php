<?php

namespace App\Models\Crm;
use App\Models\Core\User;
use App\Models\Crm\CrmInteraction;
use App\Models\Crm\LeadNote;
use App\Models\Crm\LeadInterview;
use App\Models\Crm\LeadApprovalFile;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'contact_person',
        'email',
        'phone',
        'interest_fabric',
        'estimated_value',
        'source',
        'external_id',
        'budget',
        'authority',
        'need_summary',
        'timeline',
        'next_step',
        'next_step_due',
        'status',
        'assigned_staff_id', // Critical for linking to the staff member
        'lost_reason',
    ];

    protected $casts = [
        'next_step_due' => 'date',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function interactions()
    {
        return $this->morphMany(CrmInteraction::class, 'contactable');
    }

    public function notes()
    {
        return $this->hasMany(LeadNote::class, 'lead_id');
    }

    public function interviews()
    {
        return $this->hasMany(LeadInterview::class, 'lead_id');
    }

    public function approvalFiles()
    {
        return $this->hasMany(LeadApprovalFile::class, 'lead_id');
    }

    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function logos()
    {
        return $this->hasMany(CrmLogoPartner::class, 'crm_lead_id');
    }

    public function logo()
    {
        return $this->hasOne(CrmLogoPartner::class, 'crm_lead_id')->latestOfMany();
    }

    public function contacts()
    {
        return $this->hasMany(CrmContact::class, 'lead_id');
    }

    public function opportunities()
    {
        return $this->hasMany(CrmOpportunity::class, 'lead_id');
    }

    public function activities()
    {
        return $this->hasMany(CrmActivity::class, 'lead_id');
    }
}
