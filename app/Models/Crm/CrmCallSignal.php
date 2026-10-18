<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmCallSignal extends Model
{
    protected $table = 'crm_call_signals';

    public const UPDATED_AT = null;

    protected $fillable = [
        'crm_call_id',
        'sender_id',
        'kind',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function call()
    {
        return $this->belongsTo(CrmCall::class, 'crm_call_id');
    }

    public function sender()
    {
        return $this->belongsTo(\App\Models\Core\User::class, 'sender_id');
    }
}
