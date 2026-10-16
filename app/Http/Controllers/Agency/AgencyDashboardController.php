<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Attendance;
use App\Models\Hrm\BillingStatement;
use App\Models\Hrm\ManpowerRequest;
use App\Models\Hrm\Worker;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Page 17: agency dashboard — own data snapshot (cards + alerts).
 */
class AgencyDashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\Hrm\AgencyUser $user */
        $user = $request->user('agency');
        $user->loadMissing('agency');
        $agency = $user->agency;
        $agencyId = (int) $user->agency_id;

        $openRequests = ManpowerRequest::where('agency_id', $agencyId)
            ->where('status', ManpowerRequest::STATUS_OPEN)->get()
            ->filter(fn ($r) => in_array($r->effective_status, [ManpowerRequest::STATUS_OPEN, ManpowerRequest::STATUS_PARTIAL], true))
            ->count();

        $pendingConfirm = Attendance::whereHas('worker', fn ($q) => $q->where('agency_id', $agencyId))
            ->where('approval', Attendance::APPROVAL_APPROVED)
            ->where('agency_confirmation', 'Pending')
            ->get()->filter(fn ($a) => $a->confirmable)->count();

        $unpaid = BillingStatement::where('agency_id', $agencyId)
            ->where('status', BillingStatement::STATUS_ISSUED)->count();

        $clearancesSoon = Worker::where('agency_id', $agencyId)
            ->whereNotNull('clearance_expiry')
            ->whereDate('clearance_expiry', '<=', today()->addDays(30)->toDateString())
            ->orderBy('clearance_expiry')->take(5)->get(['id', 'code', 'name', 'clearance_expiry'])
            ->map(fn ($w) => [
                'code' => $w->code, 'name' => $w->name,
                'clearance_expiry' => optional($w->clearance_expiry)->toDateString(),
                'expired' => (bool) $w->clearance_expired,
            ])->values()->all();

        return Inertia::render('Agency/Dashboard', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'agency' => $agency ? [
                'code' => $agency->code,
                'name' => $agency->name,
                'status' => $agency->effective_status,
                'contact_name' => $agency->contact_name,
                'contact_email' => $agency->contact_email,
                'contact_phone' => $agency->contact_phone,
                'contract_start' => optional($agency->contract_start)->toDateString(),
                'contract_end' => optional($agency->contract_end)->toDateString(),
                'dole_number' => $agency->dole_number,
                'dole_expiry' => optional($agency->dole_expiry)->toDateString(),
                'service_fee_type' => $agency->service_fee_type,
                'service_fee_value' => $agency->service_fee_value,
                'expiring_soon' => (bool) $agency->expiring_soon,
            ] : null,
            'cards' => $agency ? [
                'open_requests' => $openRequests,
                'workers_deployed' => Worker::where('agency_id', $agencyId)->where('status', Worker::STATUS_DEPLOYED)->count(),
                'workers_available' => Worker::where('agency_id', $agencyId)->where('status', Worker::STATUS_AVAILABLE)->count(),
                'pending_confirmation' => $pendingConfirm,
                'unpaid_statements' => $unpaid,
            ] : null,
            'clearances_soon' => $clearancesSoon,
        ]);
    }
}
