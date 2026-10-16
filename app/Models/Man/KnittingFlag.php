<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class KnittingFlag extends Model
{
    protected $fillable = [
        'code',
        'machine_id',
        'fabric_id',
        'issue_type',
        'description',
        'status',
        'reported_by',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function fabric()
    {
        return $this->belongsTo(Fabric::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
