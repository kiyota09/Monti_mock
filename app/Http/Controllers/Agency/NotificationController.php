<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AgencyActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $agencyId = Auth::guard('agency')->user()->agency_id;
        $activities = AgencyActivity::query()
            ->where('agency_id', $agencyId)
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'items' => $activities->map(fn (AgencyActivity $activity) => [
                'id' => $activity->id,
                'title' => match ($activity->action) {
                    'request_submitted' => 'Manpower request submitted',
                    'request_created' => 'Manpower request created',
                    'request_updated' => 'Manpower request updated',
                    'worker_assigned' => 'Worker deployed in Monti',
                    'worker_reported' => 'Worker report received',
                    default => $activity->action,
                },
                'details' => $activity->details,
                'reason' => $activity->reason,
                'date' => $activity->created_at?->format('M j, Y g:i A'),
            ]),
            'unread' => $activities->count(),
        ]);
    }
}
