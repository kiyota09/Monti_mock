<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmPagePermission extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'page'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}