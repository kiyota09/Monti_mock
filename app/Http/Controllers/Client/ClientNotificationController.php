<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Crm\ClientNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientNotificationController extends Controller
{
    public function index()
    {
        $clientId = Auth::guard('client')->id();

        $items = ClientNotification::where('client_id', $clientId)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'body'       => $n->body,
                'link_route' => $n->link_route,
                'is_read'    => (bool) $n->is_read,
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'items'  => $items,
            'unread' => ClientNotification::where('client_id', $clientId)->where('is_read', false)->count(),
        ]);
    }

    public function read(Request $request, ClientNotification $notification)
    {
        abort_unless($notification->client_id === Auth::guard('client')->id(), 403);
        $notification->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }

    public function readAll()
    {
        ClientNotification::where('client_id', Auth::guard('client')->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }
}
