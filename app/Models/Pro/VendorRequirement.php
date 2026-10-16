<?php

namespace App\Models\Pro;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorRequirement extends Model
{
    protected $fillable = [
        'vendor_registration_id',
        'requirement_name',
        'description',
        'value',
    ];

    /**
     * Get the vendor registration associated with this requirement
     */
    public function vendorRegistration(): BelongsTo
    {
        return $this->belongsTo(VendorRegistration::class);
    }
}
