<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\Fabric;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Man\SqueezerJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DyeingSqueezerController extends ManufacturingStaffController
{
    public function index()
    {
        $pending = $this->readyFabrics()->count();
        $recentJobs = SqueezerJob::with('fabric.salesOrder', 'machine', 'operator', 'ironJob')
            ->where('operator_id', $this->staff()->id)->latest()->take(10)->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingSqueezer/Index', [
            'stats' => ['pending' => $pending, 'total_today' => SqueezerJob::whereDate('processed_at', today())->count()],
            'recentJobs' => $recentJobs,
            'efficiency' => array_merge($this->staffEfficiency(SqueezerJob::class), ['machines' => $this->machineAvailability('squeezer')]),
            'nextQueue' => $this->readyFabrics()->orderBy('created_at')->take(3)->get(),
        ]);
    }

    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Finishing Squeezer', 'historyRoute' => 'man.staff.finishing-squeezer.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(SqueezerJob::class, ['fabric.machine', 'fabric.salesOrder.client', 'fabric.salesOrder.recipe.product', 'machine', 'operator', 'ironJob']),
        ]);
    }

    public function dyeingSqueezer()
    {
        return Inertia::render('Dashboard/MAN/Employee/DyeingSqueezer/DyeingSqueezer', [
            'fabrics' => $this->readyFabrics()->with('salesOrder')->orderBy('created_at')->get(),
            'machines' => Machine::where('type', 'squeezer')->where('status', 'available')->get(['id', 'machine_no']),
        ]);
    }

    public function storeSqueezer(Request $request)
    {
        $validated = $request->validate([
            'fabric_id' => 'required|exists:fabrics,id', 'machine_id' => 'required|exists:machines,id', 'remarks' => 'nullable|string',
        ]);
        $fabric = Fabric::findOrFail($validated['fabric_id']);
        return back()->withErrors(['fabric_id' => 'The Finishing Supervisor must assign this JO to the Squeezer or Hydro route first.']);
        if ($fabric->status !== 'squeezer') return back()->withErrors(['fabric_id' => 'This fabric is no longer waiting for squeezing.']);
        if (! $this->isAssignedItem($fabric->manufacturing_order_id, $fabric->sales_order_id)) return back()->withErrors(['fabric_id' => 'This Job Order is not assigned to you by your supervisor.']);
        if ($fabric->squeezerJobs()->whereDoesntHave('ironJob')->exists()) return back()->withErrors(['fabric_id' => 'A squeezer job is already awaiting quality check.']);

        SqueezerJob::create([
            ...$validated, 'softener_job_id' => null, 'operator_id' => $this->staff()->id, 'shift' => $this->getShift(),
            'code' => $this->generateCode('SQUEEZE', SqueezerJob::class), 'processed_at' => now(),
        ]);
        return back()->with('message', 'Squeezing recorded. Awaiting quality check before it can move to Finishing Heatsetting.');
    }

    public function reports()
    {
        return Inertia::render('Dashboard/MAN/Employee/DyeingSqueezer/Reports', [
            'machines' => Machine::where('type', 'squeezer')->get(['id', 'machine_no', 'status']),
            'myReports' => MachineReport::where('reported_by', $this->staff()->id)->with('machine')->latest()->get(),
        ]);
    }

    public function reportMachine(Request $request)
    {
        $validated = $request->validate(['machine_id' => 'required|exists:machines,id', 'issue' => 'required|string']);
        MachineReport::create(['machine_id' => $validated['machine_id'], 'reported_by' => $this->staff()->id, 'issue' => $validated['issue'], 'status' => 'pending']);
        return back()->with('message', 'Machine issue reported.');
    }

    private function readyFabrics()
    {
        $query = Fabric::where('status', 'squeezer')->whereDoesntHave('squeezerJobs', fn ($jobs) => $jobs->whereDoesntHave('ironJob'));
        $this->scopeToAssignment($query);
        return $query;
    }
}
