<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientNotification extends Model
{
    protected $fillable = [
        'client_id',
        'type',
        'title',
        'body',
        'link_route',
        'delivery_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Logistics\Delivery::class);
    }

    /**
     * Create a portal notification for a business client.
     */
    public static function notify(int $clientId, string $title, ?string $body = null, ?string $linkRoute = null, ?int $deliveryId = null, string $type = 'logistics'): self
    {
        return static::create([
            'client_id'   => $clientId,
            'type'        => $type,
            'title'       => $title,
            'body'        => $body,
            'link_route'  => $linkRoute,
            'delivery_id' => $deliveryId,
            'is_read'     => false,
        ]);
    }
}
