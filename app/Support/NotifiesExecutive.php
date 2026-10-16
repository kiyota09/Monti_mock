<?php

namespace App\Support;

use App\Models\Ceo\ExecutiveNotification;

/**
 * Push-style executive alerts. Call sites pass already-composed
 * content; delivery (inbox + badge) is automatic for CEO-role holders.
 */
class NotifiesExecutive
{
    public static function push(string $type, string $title, ?string $body = null, ?string $linkRoute = null): void
    {
        ExecutiveNotification::create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link_route' => $linkRoute,
            'is_read' => false,
            'created_by' => auth()->id(),
        ]);
    }
}
