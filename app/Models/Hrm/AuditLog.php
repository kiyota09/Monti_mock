<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * * @var array<int, string>
     */
    protected $fillable = [
        'admin_id',
        'target_id',
        'action',
        'reason',
        'target_name',
    ];

    /**
     * Get the admin user who performed the action.
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Get the employee user who was affected by the action.
     */
    public function target()
    {
        return $this->belongsTo(User::class, 'target_id');
    }
}
