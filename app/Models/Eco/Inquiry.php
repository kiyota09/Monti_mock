<?php

namespace App\Models\Eco;

use App\Models\Crm\Client;
use App\Models\Inv\Product;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    protected $table = 'inquiries';
    protected $fillable = ['client_id', 'product_id', 'initial_message', 'status', 'last_message_at'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    protected $appends = ['hash_key'];

    /**
     * Opaque URL key (see App\Support\InquiryHash) so conversation links
     * never expose the sequential numeric ID.
     */
    public function getHashKeyAttribute(): string
    {
        return \App\Support\InquiryHash::encode((int) $this->getKey());
    }

    /**
     * route('…', $inquiry) emits the hashed key instead of the raw ID.
     */
    public function getRouteKey()
    {
        return $this->hash_key;
    }

    /**
     * Implicit binding ({inquiry} in CRM + client routes) resolves hashed
     * keys only — raw numeric IDs 404 so nothing leaks.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $id = \App\Support\InquiryHash::decode((string) $value);
        if ($id === null) {
            return null;
        }

        return $this->where('id', $id)->first();
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function messages()
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function sampleRequests()
    {
        return $this->hasMany(\App\Models\Crm\FabricSampleRequest::class)->latest();
    }
}