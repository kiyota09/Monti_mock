<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\Fabric;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Man\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DyeingPackagingController extends ManufacturingStaffController
{
    public function index()
    {
        $pendingCount = Fabric::where('status', 'packed')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('packages')
                    ->whereColumn('packages.fabric_id', 'fabrics.id');
            })
            ->count();

        $recentPackages = Package::with('fabric')
            ->where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        $nextQueue = Fabric::where('status', 'packed')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('packages')
                    ->whereColumn('packages.fabric_id', 'fabrics.id');
            })
            ->orderBy('created_at', 'asc')
            ->take(3)
            ->get(['id', 'code', 'yarn_type', 'weight', 'created_at']);

        return Inertia::render('Dashboard/MAN/Employee/DyeingPackaging/Index', [
            'stats' => [
                'pending' => $pendingCount,
                'total_today' => Package::whereDate('packaged_at', today())->count(),
            ],
            'recentPackages' => $recentPackages,
            'efficiency' => array_merge(
                $this->staffEfficiency(Package::class, 'packaged_at'),
                ['machines' => $this->machineAvailability(null)]
            ),
            'nextQueue' => $nextQueue,
        ]);
    }

    /**
     * Personal work log — own packages only.
     */
    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Dyeing Packaging',
            'historyRoute' => 'man.staff.dyeing-packaging.history',
            'dateColumn' => 'packaged_at',
            'jobs' => $this->staffHistory(Package::class, ['fabric.machine', 'fabric.salesOrder.client', 'fabric.salesOrder.recipe.product', 'operator', 'items'], 'packaged_at'),
        ]);
    }

    /**
     * Machine reports (parity with other production roles).
     * Packaging staff previously had no reporting channel.
     */
    public function reports()
    {
        $machines = Machine::orderBy('type')->orderBy('machine_no')
            ->get(['id', 'machine_no', 'type', 'status']);
        $myReports = MachineReport::where('reported_by', $this->staff()->id)
            ->with('machine')->latest()->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingPackaging/Reports', [
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

    public function packaging()
    {
        $fabrics = Fabric::with('salesOrder', 'machine', 'operator')
            ->where('status', 'packed')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('packages')
                    ->whereColumn('packages.fabric_id', 'fabrics.id');
            })  // Exclude fabrics that already have a package
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingPackaging/DyeingPackaging', [
            'fabrics' => $fabrics,
        ]);
    }

    public function storePackage(Request $request)
    {
        $validated = $request->validate([
            'fabric_id' => 'required|exists:fabrics,id',
            'quantity'  => 'required|integer|min:1',
            'remarks'   => 'nullable|string',
        ]);

        $fabric = Fabric::findOrFail($validated['fabric_id']);

        // Ensure the fabric is still ready for packaging and not already packaged
        if ($fabric->status !== 'packed' || $fabric->packages()->exists()) {
            return back()->with('error', 'This fabric is no longer available for packaging.');
        }

        $package = Package::create([
            'code'          => $this->generateCode('PACKAGE', Package::class),
            'operator_id'   => $this->staff()->id,
            'shift'         => $this->getShift(),
            'packaged_at'   => now(),
            'status'        => 'pending',
            'fabric_id'     => $fabric->id,
            'quantity'      => $validated['quantity'],   // store the quantity
        ]);

        // Fabric status remains 'packed' – the package existence prevents it from reappearing.

        return redirect()->back()->with('message', 'Package created successfully.');
    }
}