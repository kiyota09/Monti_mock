<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Man\SoftenerJob;
use App\Models\Man\SqueezerJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DyeingSqueezerController extends ManufacturingStaffController
{
    public function index()
    {
        // Checker gate: only softener jobs whose fabric the quality checker
        // already approved to 'squeezer' may be squeezed. Recorded-but-
        // unapproved work stays with the checker, never here.
        $pendingCount = SoftenerJob::where('status', 'softened')
            ->whereHas('fabric', fn ($q) => $q->where('status', 'squeezer'))
            ->count();
        $recentJobs = SqueezerJob::with('softenerJob.fabric')
            ->where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        $nextQueue = SoftenerJob::with('fabric')
            ->where('status', 'softened')
            ->whereHas('fabric', fn ($q) => $q->where('status', 'squeezer'))
            ->orderBy('created_at', 'asc')
            ->take(3)
            ->get(['id', 'code', 'fabric_id', 'created_at']);

        return Inertia::render('Dashboard/MAN/Employee/DyeingSqueezer/Index', [
            'stats' => [
                'pending' => $pendingCount,
                'total_today' => SqueezerJob::whereDate('processed_at', today())->count(),
            ],
            'recentJobs' => $recentJobs,
            'efficiency' => array_merge(
                $this->staffEfficiency(SqueezerJob::class),
                ['machines' => $this->machineAvailability('squeezer')]
            ),
            'nextQueue' => $nextQueue,
        ]);
    }

    /**
     * Personal work log — own squeezer jobs only.
     */
    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Dyeing Squeezer',
            'historyRoute' => 'man.staff.dyeing-squeezer.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(SqueezerJob::class, ['softenerJob.fabric.machine', 'softenerJob.fabric.salesOrder.client', 'softenerJob.fabric.salesOrder.recipe.product', 'machine', 'operator', 'ironJob']),
        ]);
    }

    public function dyeingSqueezer()
    {
        // Only checker-approved work: softened jobs whose fabric sits at
        // 'squeezer'. Anything still awaiting softener QC is invisible here.
        $softenerJobs = SoftenerJob::with('fabric')
            ->where('status', 'softened')
            ->whereHas('fabric', fn ($q) => $q->where('status', 'squeezer'))
            ->get();

        $machines = Machine::where('type', 'squeezer')
            ->where('status', 'available')
            ->get(['id', 'machine_no']);

        return Inertia::render('Dashboard/MAN/Employee/DyeingSqueezer/DyeingSqueezer', [
            'softenerJobs' => $softenerJobs,
            'machines' => $machines,
        ]);
    }

    public function storeSqueezer(Request $request)
    {
        $validated = $request->validate([
            'softener_job_id' => 'required|exists:softener_jobs,id',
            'machine_id' => 'required|exists:machines,id',
            'remarks' => 'nullable|string',
        ]);

        $softenerJob = SoftenerJob::with('fabric')->findOrFail($validated['softener_job_id']);

        // Checker gate: the fabric must already sit at 'squeezer' (checker
        // approved the softener job). Squeezing unapproved work is blocked.
        if ($softenerJob->status !== 'softened') {
            return back()->withErrors(['softener_job_id' => 'This softener job was already squeezed — awaiting quality check.']);
        }
        if ($softenerJob->fabric?->status !== 'squeezer') {
            return back()->withErrors(['softener_job_id' => 'This fabric has not been approved to squeezing yet — awaiting quality check.']);
        }

        $softenerJob->update(['status' => 'squeezed']);

        // Checker gate: fabric stays 'squeezer' until the quality checker
        // approves it (passSqueezer → iron) or sends it back for rework.
        SqueezerJob::create([
            'softener_job_id' => $validated['softener_job_id'],
            'machine_id' => $validated['machine_id'],
            'remarks' => $validated['remarks'],
            'operator_id' => $this->staff()->id,
            'shift' => $this->getShift(),
            'code' => $this->generateCode('SQUEEZE', SqueezerJob::class),
            'processed_at' => now(),
        ]);

        return redirect()->back()->with('message', 'Squeezing recorded successfully. Awaiting quality check before it can move to ironing.');
    }

    public function reports()
    {
        $machines = Machine::where('type', 'squeezer')->get(['id', 'machine_no', 'status']);
        $myReports = MachineReport::where('reported_by', $this->staff()->id)
            ->with('machine')
            ->latest()
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingSqueezer/Reports', [
            'machines' => $machines,
            'myReports' => $myReports,
        ]);
    }

    public function reportMachine(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'issue' => 'required|string',
        ]);

        MachineReport::create([
            'machine_id' => $validated['machine_id'],
            'reported_by' => $this->staff()->id,
            'issue' => $validated['issue'],
            'status' => 'pending',
        ]);

        return redirect()->back()->with('message', 'Machine issue reported.');
    }
}
