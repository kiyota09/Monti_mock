<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantNotification extends Model
{
    protected $table = 'applicant_notifications';
    protected $fillable = ['applicant_id', 'type', 'title', 'message', 'data', 'read_at'];
    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function toPortalArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => array_merge($this->data ?? [], [
                'type' => $this->type,
                'title' => $this->title,
                'message' => $this->message,
            ]),
            'read_at' => optional($this->read_at)?->toISOString(),
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}
