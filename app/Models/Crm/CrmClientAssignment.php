<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmClientAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'staff_id', 'assigned_by'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}