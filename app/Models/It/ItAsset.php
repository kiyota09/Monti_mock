<?php

namespace App\Models\It;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItAsset extends Model
{
    protected $table = 'it_assets';

    protected $fillable = [
        'asset_code',
        'name',
        'category',
        'type',
        'serial_number',
        'specs',
        'location',
        'status',
        'assigned_to_user_id',
        'purchase_date',
        'warranty_end',
        'vendor',
        'cost',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_end' => 'date',
        'cost' => 'decimal:2',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ItAssetAssignment::class, 'asset_id')->latest('assigned_at');
    }

    public function scopeExpiring($query, int $days = 30)
    {
        return $query->whereNotNull('warranty_end')
            ->where('warranty_end', '>=', now()->toDateString())
            ->where('warranty_end', '<=', now()->addDays($days)->toDateString());
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('warranty_end')
            ->where('warranty_end', '<', now()->toDateString());
    }

    public function getIsWarrantyExpiredAttribute(): bool
    {
        return $this->warranty_end && $this->warranty_end->isPast();
    }

    public function getIsWarrantyExpiringAttribute(): bool
    {
        return $this->warranty_end
            && ! $this->is_warranty_expired
            && $this->warranty_end->lte(now()->addDays(30));
    }
}
