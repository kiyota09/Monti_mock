<?php

namespace App\Models\Scm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class ScmAccessPermission extends Model
{
    protected $fillable = ['user_id', 'granted_by', 'can_access_scm'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}