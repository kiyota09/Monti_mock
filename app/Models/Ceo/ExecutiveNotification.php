<?php

namespace App\Models\Ceo;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class ExecutiveNotification extends Model
{
    protected $fillable = [
        'type', 'title', 'body', 'link_route', 'is_read', 'created_by',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
