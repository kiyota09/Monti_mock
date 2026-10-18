<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AgencyActivity;
use Illuminate\Http\Request;

/**
 * Agency portal notifications (bell next to Settings).
 * Source of truth is agency_activities for the agency — HR approvals,
 * correction requests, assignments, releases, proposals, attendance, billing.
 * Read state is tracked client-side (no new columns).
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $agencyId = (int) $request->user('agency')->agency_id;

        $items = AgencyActivity::with(['worker:id,code,name', 'request:id,code'])
            ->where('agency_id', $agencyId)
            ->latest()
            ->take(15)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'title' => $this->title($a->action),
                'details' => $a->details,
                'actor' => $a->actor_name ?? ('#' . $a->actor_id),
                'reason' => $a->reason,
                'worker_code' => $a->worker?->code,
                'worker_name' => $a->worker?->name,
                'request_code' => $a->request?->code,
                'request_id' => $a->request_id,
                'created_at' => optional($a->created_at)->toISOString(),
                'date' => optional($a->created_at)->format('M d, Y h:i A'),
            ])->values()->all();

        $unread = AgencyActivity::where('agency_id', $agencyId)
            ->where('created_at', '>=', now()->subHours(72))
            ->count();

        return response()->json(['items' => $items, 'unread' => $unread]);
    }

    protected function title(string $action): string
    {
        return match ($action) {
            'worker_created' => 'Worker submitted',
            'worker_approved' => 'Worker approved',
            'worker_correction_requested' => 'Correction requested',
            'worker_resubmitted' => 'Resubmitted',
            'worker_rejected' => 'Worker rejected',
            'worker_assigned' => 'Worker assigned',
            'worker_released' => 'Worker released',
            'worker_proposed' => 'Worker proposed',
            'proposal_rejected' => 'Proposal rejected',
            'request_submitted' => 'New request',
            'attendance_recorded' => 'Attendance recorded',
            'attendance_confirmed' => 'Attendance confirmed',
            'attendance_disputed' => 'Attendance disputed',
            'billing_issued' => 'Billing issued',
            'billing_paid' => 'Billing paid',
            'statement_created' => 'Billing draft',
            'statement_issued' => 'Billing issued',
            'statement_paid' => 'Billing paid',
            'statement_cancelled' => 'Billing cancelled',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }
}
