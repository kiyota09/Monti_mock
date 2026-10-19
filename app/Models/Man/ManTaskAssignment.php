<?php

namespace App\Models\Man;

use App\Models\Core\User;
use App\Models\Ord\SalesOrder;
use Illuminate\Database\Eloquent\Model;

class ManTaskAssignment extends Model
{
    protected $table = 'man_task_assignments';

    protected $fillable = [
        'manufacturing_order_id',
        'sales_order_id',
        'fabric_id',
        'department',
        'stage',
        'assigned_role',
        'assigned_to',
        'assigned_by',
        'status',
        'sequence',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function fabric()
    {
        return $this->belongsTo(Fabric::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }
}
