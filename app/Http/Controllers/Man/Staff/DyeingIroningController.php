<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\IronJob;
use App\Models\Man\MachineReport;
use App\Models\Man\SqueezerJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DyeingIroningController extends ManufacturingStaffController
{
    public function index()
    {
        // Checker gate: only squeezer jobs whose fabric the quality checker
        // already approved to 'iron' may be ironed.
        $pendingCount = SqueezerJob::whereDoesntHave('ironJob')
            ->whereHas('softenerJob.fabric', fn ($q) => $q->where('status', 'iron'))
            ->count();

        $recentJobs = IronJob::with('squeezerJob.softenerJob.fabric')
            ->where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        $nextQueue = SqueezerJob::with('softenerJob.fabric')
            ->whereDoesntHave('ironJob')
            ->whereHas('softenerJob.fabric', fn ($q) => $q->where('status', 'iron'))
            ->orderBy('created_at', 'asc')
            ->take(3)
            ->get(['id', 'code', 'created_at']);

        return Inertia::render('Dashboard/MAN/Employee/DyeingIroning/Index', [
            'stats' => [
                'pending' => $pendingCount,
                'total_today' => IronJob::whereDate('processed_at', today())->count(),
            ],
            'recentJobs' => $recentJobs,
            // Ironing has no dedicated machine type — machine context is null.
            'efficiency' => array_merge(
                $this->staffEfficiency(IronJob::class),
                ['machines' => $this->machineAvailability(null)]
            ),
            'nextQueue' => $nextQueue,
        ]);
    }

    /**
     * Personal work log — own iron jobs only.
     */
    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Dyeing Ironing',
            'historyRoute' => 'man.staff.dyeing-ironing.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(IronJob::class, ['squeezerJob.softenerJob.fabric.machine', 'squeezerJob.softenerJob.fabric.salesOrder.client', 'squeezerJob.softenerJob.fabric.salesOrder.recipe.product', 'squeezerJob.machine', 'operator']),
        ]);
    }

    public function dyeingIroning()
    {
        // Only checker-approved work: squeezer jobs without an iron job yet
        // whose fabric sits at 'iron'. Anything still awaiting squeezer QC
        // is invisible here.
        $squeezerJobs = SqueezerJob::with('softenerJob.fabric')
            ->whereDoesntHave('ironJob')
            ->whereHas('softenerJob.fabric', fn ($q) => $q->where('status', 'iron'))
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingIroning/DyeingIroning', [
            'squeezerJobs' => $squeezerJobs,
        ]);
    }

    public function storeIron(Request $request)
    {
        $validated = $request->validate([
            'squeezer_job_id' => 'required|exists:squeezer_jobs,id',
            'remarks' => 'nullable|string',
        ]);

        $squeezerJob = SqueezerJob::with('softenerJob.fabric')->findOrFail($validated['squeezer_job_id']);

        // Checker gate: the fabric must already sit at 'iron' (checker
        // approved the squeezer job). Ironing unapproved work is blocked.
        $fabric = $squeezerJob->softenerJob?->fabric;
        if (! $fabric || $fabric->status !== 'iron') {
            return back()->withErrors(['squeezer_job_id' => 'This fabric has not been approved to ironing yet — awaiting quality check.']);
        }
        if ($squeezerJob->ironJob()->exists()) {
            return back()->withErrors(['squeezer_job_id' => 'An iron job is already recorded for this fabric — awaiting quality check.']);
        }

        IronJob::create([
            'squeezer_job_id' => $validated['squeezer_job_id'],
            'remarks' => $validated['remarks'],
            'operator_id' => $this->staff()->id,
            'shift' => $this->getShift(),
            'code' => $this->generateCode('IRON', IronJob::class),
            'processed_at' => now(),
        ]);

        // Checker gate: fabric stays 'iron' until the quality checker
        // approves it (passIron → packed) or sends it back for rework.
        // (Forming step removed: approved ironing flows directly to packaging.)
        return redirect()->back()->with('message', 'Ironing recorded successfully. Awaiting quality check before it can move to packaging.');
    }

    public function reports()
    {
        $myReports = MachineReport::where('reported_by', $this->staff()->id)
            ->with('machine')
            ->latest()
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingIroning/Reports', [
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