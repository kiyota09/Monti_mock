<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantDocument extends Model
{
    protected $table = 'applicant_documents';
    protected $fillable = [
        'applicant_id', 'type', 'file_path', 'original_name', 'verified', 'uploaded_by',
    ];
    protected $casts = ['verified' => 'boolean'];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
