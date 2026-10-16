<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HrmInterview extends Model
{
    protected $table = 'hrm_interviews';
    protected $fillable = [
        'applicant_id', 'job_posting_id', 'candidate_name', 'position', 'type',
        'interviewer_id', 'date', 'start_time', 'end_time', 'method', 'location',
        'meeting_link', 'notes', 'status', 'cancel_reason',
    ];
    protected $casts = ['date' => 'date'];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(HrmJobPosting::class, 'job_posting_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(HrmInterviewEvaluation::class, 'interview_id');
    }
}
