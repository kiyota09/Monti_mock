<?php

namespace App\Models\Pro;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Supplier extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'business_name',
        'representative_name',
        'address',
        'email',
        'phone_number',
        'password',
        'status',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'password' => 'hashed',
    ];

    /**
     * Get the vendor registration associated with this supplier
     */
    public function vendorRegistration(): HasOne
    {
        return $this->hasOne(VendorRegistration::class);
    }

    /**
     * Raw materials this supplier carries (linked to Monti inventory
     * materials, so names/categories stay consistent for procurement
     * matching).
     */
    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /**
     * Check if this supplier is approved
     */
    public function isApproved(): bool
    {
        return $this->vendorRegistration?->status === 'approved';
    }

    /**
     * Get vendor requirements
     */
    public function requirements()
    {
        return $this->vendorRegistration?->requirements ?? collect();
    }
}
