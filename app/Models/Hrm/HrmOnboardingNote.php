<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;

class HrmOnboardingNote extends Model
{
    protected $table = 'hrm_onboarding_notes';
    protected $fillable = ['onboarding_id', 'user_id', 'content', 'is_hr_private'];
    protected $casts = ['is_hr_private' => 'boolean'];
}
