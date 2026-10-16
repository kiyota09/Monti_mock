<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\PcoRecord;
use App\Support\NotifiesExecutive;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PollutionControlController extends ManufacturingStaffController
{
    public function index()
    {
        $recentRecords = PcoRecord::where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/PollutionControl/Index', [
            'stats' => [
                'records_today' => PcoRecord::whereDate('processed_at', today())->count(),
                'non_compliant' => PcoRecord::where('compliant', false)->count(),
                'records_week' => PcoRecord::whereBetween('processed_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            ],
            'recentRecords' => $recentRecords,
            'efficiency' => $this->staffEfficiency(PcoRecord::class),
        ]);
    }

    /**
     * Environment compliance log work page.
     */
    public function logs()
    {
        $records = PcoRecord::with('operator')
            ->latest()
            ->take(50)
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/PollutionControl/EnvLog', [
            'records' => $records,
        ]);
    }

    public function storeRecord(Request $request)
    {
        $validated = $request->validate([
            'record_type' => 'required|in:effluent,emission,solid_waste,chemical_handling,noise',
            'location' => 'nullable|string|max:255',
            'parameter' => 'nullable|string|max:255',
            'value' => 'nullable|string|max:64',
            'unit' => 'nullable|string|max:32',
            'standard_limit' => 'nullable|string|max:64',
            'compliant' => 'nullable|boolean',
            'recorded_date' => 'required|date',
            'remarks' => 'nullable|string',
        ]);

        PcoRecord::create([
            ...$validated,
            'code' => $this->generateCode('PCO', PcoRecord::class),
            'compliant' => (bool) ($validated['compliant'] ?? true),
            'operator_id' => $this->staff()->id,
            'shift' => $this->getShift(),
            'processed_at' => now(),
        ]);

        if (! (bool) ($validated['compliant'] ?? true)) {
            NotifiesExecutive::push('compliance', 'Non-compliant environment reading', ($validated['parameter'] ?? 'Reading') . ' at ' . ($validated['location'] ?? 'plant') . ' breached its DENR limit.', 'ceo.approvals');
        }

        return redirect()->back()->with('message', 'Environment record logged successfully.');
    }

    /**
     * Personal work log — own records only.
     */
    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Pollution Control Operator',
            'historyRoute' => 'man.staff.pollution-control.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(PcoRecord::class),
        ]);
    }
}
