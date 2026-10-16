<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Ceo\ExecutiveNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CeoNotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = ExecutiveNotification::latest();

        if ($request->filter === 'unread') {
            $query->where('is_read', false);
        }

        return Inertia::render('Dashboard/CEO/Inbox', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'filter' => $request->get('filter', 'all'),
            'unreadCount' => ExecutiveNotification::where('is_read', false)->count(),
        ]);
    }

    public function markRead(ExecutiveNotification $notification)
    {
        $notification->update(['is_read' => true]);

        return back();
    }

    public function markAllRead()
    {
        ExecutiveNotification::where('is_read', false)->update(['is_read' => true]);

        return back()->with('message', 'Inbox cleared.');
    }
}
