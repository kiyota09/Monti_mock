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
        'process',
        'assigned_role',
        'assigned_to',
        'assigned_by',
        'deadline',
        'status',
        'sequence',
        'queue_position',
        'notes',
        'assigned_weight',
        'dyeing_remake_request_id',
        'machine_ids',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'deadline' => 'date',
        'assigned_weight' => 'decimal:2',
        'machine_ids' => 'array',
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

    public function dyeingRemakeRequest()
    {
        return $this->belongsTo(DyeingRemakeRequest::class, 'dyeing_remake_request_id');
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
