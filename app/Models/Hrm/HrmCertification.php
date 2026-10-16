<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmCertification extends Model
{
    protected $table = 'hrm_certifications';
    protected $fillable = ['user_id', 'training_id', 'name', 'issued_at', 'expiry_date'];
    protected $casts = ['issued_at' => 'date', 'expiry_date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
