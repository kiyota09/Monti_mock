<?php

namespace App\Models\Man;

use App\Models\Core\User;
use App\Models\Ord\SalesOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fabric extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'manufacturing_order_id',
        'sales_order_id',
        'machine_id',
        'yarn_type',
        'weight',
        'remarks',
        'operator_id',
        'shift',
        'processed_at',
        'status',
        'rejection_action',   // 'recolor' or 'total'
        'rejection_reason',   // text
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function dyeJobs()
    {
        return $this->hasMany(DyeJob::class);
    }

    public function softenerJobs()
    {
        return $this->hasMany(SoftenerJob::class);
    }

    /**
     * Get the packages created from this fabric.
     */
    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Scope a query to only include rejected fabrics.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope a query to only include fabrics pending for softener.
     */
    public function scopePendingSoftener($query)
    {
        return $query->where('status', 'softener');
    }
}
