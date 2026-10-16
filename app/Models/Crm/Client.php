<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Eco\CreditAccount;
use App\Models\Ord\PurchaseOrder;

class Client extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_name',
        'business_type',
        'tin_number',
        'contact_person',
        'email',
        'password',
        'phone',
        'company_address',
        'city',
        'province',
        'postal_code',
        'status',
        'credit_limit',
        'payment_terms_days',
        'latitude',   // ← ADDED
        'longitude',  // ← ADDED
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'credit_limit' => 'decimal:2',
        'payment_terms_days' => 'integer',
        'latitude' => 'float',  // ← ADDED
        'longitude' => 'float',  // ← ADDED
    ];

    protected $appends = ['hash_key'];

    /**
     * Opaque URL key (see App\Support\RouteHash) so customer-profile links
     * never expose the sequential numeric ID.
     */
    public function getHashKeyAttribute(): string
    {
        return \App\Support\RouteHash::encode((int) $this->getKey(), 'client');
    }

    /**
     * route('…', $client) emits the hashed key instead of the raw ID.
     */
    public function getRouteKey()
    {
        return $this->hash_key;
    }

    /**
     * Implicit binding ({client} params) resolves hashed keys only —
     * raw numeric IDs 404 so nothing leaks.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $id = \App\Support\RouteHash::decode((string) $value, 'client');
        if ($id === null) {
            return null;
        }

        return $this->where('id', $id)->first();
    }

    /**
     * Scope a query to only include active clients.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function meetings()
    {
        return $this->hasMany(CrmMeeting::class);
    }

    public function feedback()
    {
        return $this->hasMany(CrmFeedback::class);
    }

    public function creditAccount()
    {
        return $this->hasOne(CreditAccount::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function logos()
    {
        return $this->hasMany(CrmLogoPartner::class);
    }

    public function logo()
    {
        return $this->hasOne(CrmLogoPartner::class)->latestOfMany();
    }

    public function contacts()
    {
        return $this->hasMany(CrmContact::class);
    }

    public function opportunities()
    {
        return $this->hasMany(CrmOpportunity::class)->latest();
    }

    public function activities()
    {
        return $this->hasMany(CrmActivity::class)->latest();
    }

    public function cases()
    {
        return $this->hasMany(CrmCase::class)->latest();
    }
}
