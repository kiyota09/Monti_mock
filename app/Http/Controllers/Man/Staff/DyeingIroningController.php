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
        $ready = $this->readySqueezerJobs()->count();
        return Inertia::render('Dashboard/MAN/Employee/DyeingIroning/Index', [
            'stats' => ['pending' => $ready, 'total_today' => IronJob::whereDate('processed_at', today())->count()],
            'recentJobs' => IronJob::with('squeezerJob.fabric', 'operator')->where('operator_id', $this->staff()->id)->latest()->take(10)->get(),
            'efficiency' => array_merge($this->staffEfficiency(IronJob::class), ['machines' => $this->machineAvailability(null)]),
            'nextQueue' => $this->readySqueezerJobs()->with('fabric')->orderBy('created_at')->take(3)->get(),
        ]);
    }

    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Finishing Heatsetting', 'historyRoute' => 'man.staff.finishing-ironing.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(IronJob::class, ['squeezerJob.fabric.machine', 'squeezerJob.fabric.salesOrder.client', 'squeezerJob.fabric.salesOrder.recipe.product', 'operator']),
        ]);
    }

    public function dyeingIroning()
    {
        return Inertia::render('Dashboard/MAN/Employee/DyeingIroning/DyeingIroning', [
            'squeezerJobs' => $this->readySqueezerJobs()->with('fabric.salesOrder')->orderBy('created_at')->get(),
        ]);
    }

    public function storeIron(Request $request)
    {
        $validated = $request->validate(['squeezer_job_id' => 'required|exists:squeezer_jobs,id', 'remarks' => 'nullable|string']);
        $squeezerJob = SqueezerJob::with('fabric')->findOrFail($validated['squeezer_job_id']);
        $fabric = $squeezerJob->fabric;
        return back()->withErrors(['squeezer_job_id' => 'The Finishing Supervisor assigns the Heatsetting or Tubetex route after Water Extraction.']);
        if (! $fabric || $fabric->status !== 'iron') return back()->withErrors(['squeezer_job_id' => 'This fabric is not approved and ready for ironing.']);
        if ($squeezerJob->ironJob()->exists()) return back()->withErrors(['squeezer_job_id' => 'An ironing job is already awaiting quality check.']);
        if (! $this->isAssignedItem($fabric->manufacturing_order_id, $fabric->sales_order_id)) return back()->withErrors(['squeezer_job_id' => 'This Job Order is not assigned to you by your supervisor.']);

        IronJob::create([
            ...$validated, 'operator_id' => $this->staff()->id, 'shift' => $this->getShift(),
            'code' => $this->generateCode('IRON', IronJob::class), 'processed_at' => now(),
        ]);
        return back()->with('message', 'Ironing recorded. Awaiting quality check before it can move to packaging.');
    }

    public function reports()
    {
        return Inertia::render('Dashboard/MAN/Employee/DyeingIroning/Reports', [
            'myReports' => MachineReport::where('reported_by', $this->staff()->id)->with('machine')->latest()->get(),
        ]);
    }

    public function reportMachine(Request $request)
    {
        $validated = $request->validate(['machine_id' => 'required|exists:machines,id', 'issue' => 'required|string']);
        MachineReport::create(['machine_id' => $validated['machine_id'], 'reported_by' => $this->staff()->id, 'issue' => $validated['issue'], 'status' => 'pending']);
        return back()->with('message', 'Machine issue reported.');
    }

    private function readySqueezerJobs()
    {
        $query = SqueezerJob::whereDoesntHave('ironJob')->whereHas('fabric', function ($fabric) {
            $fabric->where('status', 'iron');
            $this->scopeToAssignment($fabric);
        });
        return $query;
    }
}
