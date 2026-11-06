<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\KnittingSampleRequest;
use App\Models\Man\Machine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KnittingSampleController extends ManufacturingStaffController
{
    public function dashboard()
    {
        $staffId = $this->staff()->id;
        $myCompleted = KnittingSampleRequest::where('operator_id', $staffId)->where('status', 'completed');

        return Inertia::render('Dashboard/MAN/Employee/KnittingSample/Dashboard', [
            'stats' => [
                'waiting' => KnittingSampleRequest::where('status', 'requested')->count(),
                'in_progress' => KnittingSampleRequest::where('status', 'in_progress')->count(),
                'my_completed_today' => (clone $myCompleted)->whereDate('processed_at', today())->count(),
                'my_completed_total' => (clone $myCompleted)->count(),
            ],
            'nextRequests' => KnittingSampleRequest::with('fabricSampleRequest:id,code,fabric_name')
                ->where('status', 'requested')->oldest()->take(5)->get(),
            'myRecent' => (clone $myCompleted)->with('fabricSampleRequest:id,code,fabric_name')
                ->latest('processed_at')->take(5)->get(),
        ]);
    }

    public function index()
    {
        return Inertia::render('Dashboard/MAN/Employee/KnittingSample/Index', [
            'requests' => KnittingSampleRequest::with('fabricSampleRequest:id,code,fabric_name')
                ->whereIn('status', ['requested', 'in_progress'])->oldest()->get(),
            'machines' => Machine::where('type', 'knitting')->where('status', 'available')->get(['id', 'machine_no']),
            'recent' => KnittingSampleRequest::with([
                'fabricSampleRequest:id,code,fabric_name,color_description,urgency,request_method',
                'machine:id,machine_no',
            ])
                ->where('operator_id', $this->staff()->id)->where('status', 'completed')->latest('processed_at')->take(10)->get(),
        ]);
    }

    public function history()
    {
        $query = KnittingSampleRequest::with(['fabricSampleRequest', 'machine', 'operator'])
            ->where('operator_id', $this->staff()->id)
            ->where('status', 'completed');

        if ($search = request('search')) {
            $query->where('code', 'like', "%{$search}%");
        }
        if ($from = request('from')) {
            $query->whereDate('processed_at', '>=', $from);
        }
        if ($to = request('to')) {
            $query->whereDate('processed_at', '<=', $to);
        }

        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Knitting Sample',
            'historyRoute' => 'man.staff.knitting-sample.history',
            'dateColumn' => 'processed_at',
            'jobs' => $query->latest('processed_at')->paginate(15)->withQueryString(),
        ]);
    }

    public function reports()
    {
        $staffId = $this->staff()->id;
        $myRequests = KnittingSampleRequest::where('operator_id', $staffId);
        $completed = (clone $myRequests)->where('status', 'completed');

        return Inertia::render('Dashboard/MAN/Employee/KnittingSample/Reports', [
            'stats' => [
                'completed' => (clone $completed)->count(),
                'completed_today' => (clone $completed)->whereDate('processed_at', today())->count(),
                'completed_this_week' => (clone $completed)->whereBetween('processed_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'produced_weight' => (float) (clone $completed)->sum('produced_weight'),
                'in_progress' => (clone $myRequests)->where('status', 'in_progress')->count(),
            ],
            'recent' => (clone $completed)->with(['fabricSampleRequest:id,code,fabric_name', 'machine:id,machine_no'])
                ->latest('processed_at')->take(15)->get(),
        ]);
    }

    public function start(KnittingSampleRequest $sample)
    {
        if ($sample->status !== 'requested') return back()->withErrors(['error' => 'This sample request is already being handled.']);
        $sample->update(['status' => 'in_progress', 'operator_id' => $this->staff()->id]);
        return back();
    }

    public function complete(Request $request, KnittingSampleRequest $sample)
    {
        if (!in_array($sample->status, ['requested', 'in_progress'], true)) return back()->withErrors(['error' => 'This sample request is already complete.']);
        $data = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'produced_weight' => 'required|numeric|min:0.001|max:1',
            'remarks' => 'nullable|string|max:2000',
        ]);
        $machine = Machine::whereKey($data['machine_id'])->where('type', 'knitting')->where('status', 'available')->firstOrFail();
        $sample->update([
            'status' => 'completed', 'operator_id' => $this->staff()->id,
            'machine_id' => $machine->id, 'produced_weight' => $data['produced_weight'],
            'remarks' => $data['remarks'] ?? null, 'processed_at' => now(),
        ]);
        return back()->with('message', "Sample fabric {$sample->code} recorded as completed.");
    }
}
