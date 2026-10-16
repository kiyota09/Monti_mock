<?php

namespace App\Models\Pro;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorRegistration extends Model
{
    protected $fillable = [
        'supplier_id',
        'business_name',
        'representative_name',
        'email',
        'phone_number',
        'address',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Get the supplier associated with this registration
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user who approved this registration
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who rejected this registration
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the vendor requirements
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(VendorRequirement::class);
    }

    /**
     * Scope to get pending registrations
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved registrations
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get rejected registrations
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
