<?php

namespace App\Models\Crm;

use App\Support\RouteHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CrmEmailThread extends Model
{
    protected $fillable = [
        'client_id',
        'contact_id',
        'from_email',
        'from_name',
        'subject',
        'last_message_preview',
        'last_message_at',
        'registration_sent_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'registration_sent_at' => 'datetime',
    ];

    protected $appends = ['hash_key'];

    public function getHashKeyAttribute(): string
    {
        return RouteHash::encode((int) $this->getKey(), 'crm-email-thread');
    }

    public function getRouteKey()
    {
        return $this->hash_key;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $id = RouteHash::decode((string) $value, 'crm-email-thread');
        if ($id === null) {
            return null;
        }

        return $this->where('id', $id)->first();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CrmEmailMessage::class, 'thread_id')->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(CrmEmailMessage::class, 'thread_id')->latestOfMany();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function invites(): HasMany
    {
        return $this->hasMany(CrmRegistrationInvite::class, 'thread_id')->latest('id');
    }

    public function scopeUnread($query)
    {
        return $query->whereHas('messages', fn ($q) => $q
            ->where('direction', 'inbound')
            ->whereNull('read_at'));
    }
}
