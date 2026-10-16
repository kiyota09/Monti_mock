<?php

namespace App\Http\Controllers\Vp;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AttendanceLog;
use App\Models\Logistics\Delivery;
use App\Models\Man\Fabric;
use App\Models\Man\LabDipRequest;
use App\Models\Man\Machine;
use App\Models\Man\Package;
use App\Models\Man\SafetyIncident;
use App\Models\Man\PcoRecord;
use App\Models\Ord\SalesOrder;
use App\Models\Vp\ExecutiveDirective;
use Inertia\Inertia;

class VpOperationsController extends Controller
{
    /**
     * Operations Command: today's plant pulse for the VP (execution view).
     * Read-only aggregates — the VP drives action via Directives.
     */
    public function index()
    {
        $today = today()->toDateString();

        $attendance = AttendanceLog::whereDate('date', $today)
            ->selectRaw('status, COUNT(*) c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $deliveries = Delivery::selectRaw('status, COUNT(*) c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return Inertia::render('Dashboard/VP/Operations', [
            'pulse' => [
                'fabrics_today' => Fabric::whereDate('processed_at', $today)->count(),
                'packages_today' => Package::whereDate('packaged_at', $today)->count(),
                'jobs_in_production' => SalesOrder::where('status', 'in_production')->count(),
                'machines_down' => Machine::where('status', '!=', 'available')->count(),
                'open_incidents' => SafetyIncident::whereIn('status', ['open', 'investigating'])->count(),
                'non_compliant' => PcoRecord::where('compliant', false)->count(),
                'pending_lab_dips' => LabDipRequest::whereIn('status', ['pending', 'in_progress', 'awaiting_spectro'])->count(),
                'open_directives' => ExecutiveDirective::whereIn('status', ['open', 'in_progress'])->count(),
                'overdue_directives' => ExecutiveDirective::whereIn('status', ['open', 'in_progress'])
                    ->whereDate('due_date', '<', $today)->count(),
            ],
            'attendance' => $attendance,
            'deliveries' => $deliveries,
            'criticalIncidents' => SafetyIncident::whereIn('severity', ['major', 'critical'])
                ->whereIn('status', ['open', 'investigating'])
                ->latest()->take(5)->get(['id', 'code', 'incident_type', 'severity', 'location', 'status']),
        ]);
    }
}
