<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AgencyActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
                    'document_verified' => 'Supporting document approved',
                    'document_rejected' => 'Supporting document rejected',
                    'document_resubmit_requested' => 'Document resubmission requested',
                    default => $activity->action,
                },
                'details' => $activity->details,
                'reason' => $activity->reason,
                'date' => $activity->created_at?->format('M j, Y g:i A'),
                'attachment_name' => $activity->attachment_name,
                'attachment_url' => $activity->attachment_path ? Storage::disk('public')->url($activity->attachment_path) : null,
            ]),
            'unread' => $activities->count(),
        ]);
    }
}
