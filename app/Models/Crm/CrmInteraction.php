<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'contactable_id',
        'contactable_type',
        'user_id',
        'type',
        'note',
    ];

    public function contactable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
