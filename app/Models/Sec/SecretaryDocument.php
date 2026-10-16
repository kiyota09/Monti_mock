<?php

namespace App\Models\Sec;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class SecretaryDocument extends Model
{
    protected $fillable = [
        'ref_no', 'direction', 'sender', 'recipient', 'subject',
        'concerned_module', 'received_date', 'deadline', 'status',
        'file_path', 'remarks', 'created_by',
    ];

    protected $casts = [
        'received_date' => 'date',
        'deadline' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
