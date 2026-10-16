<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmTraining extends Model
{
    protected $table = 'hrm_trainings';
    protected $fillable = ['name', 'description', 'status', 'duration_hours', 'expiry_days', 'created_by'];

    public function enrollments(): HasMany
    {
        return $this->hasMany(HrmTrainingEnrollment::class, 'training_id');
    }
}
