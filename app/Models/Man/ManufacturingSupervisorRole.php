<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManufacturingSupervisorRole extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'manufacturing_role'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
