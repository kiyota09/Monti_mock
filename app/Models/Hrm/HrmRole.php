<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmRole extends Model
{
    protected $table = 'hrm_roles';
    protected $fillable = ['code', 'name', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'hrm_role_id');
    }
}
