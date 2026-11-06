<?php

namespace App\Http\Controllers\Man\Staff;

use App\Http\Controllers\Man\Concerns\HandlesProductionGates;
use App\Models\Man\Fabric;
use App\Models\Man\KnittingInspection;
use Inertia\Inertia;

class KnittingInspectorController extends ManufacturingStaffController
{
    use HandlesProductionGates;

    public function dashboard()
    {
        $inspectorId = $this->staff()->id;
        $decisions = KnittingInspection::where('inspector_id', $inspectorId);

        return Inertia::render('Dashboard/MAN/Employee/KnittingInspector/Dashboard', [
            'stats' => [
                'waiting' => Fabric::where('status', 'pending')->count(),
                'approved' => (clone $decisions)->where('result', 'approved')->count(),
                'returned' => (clone $decisions)->where('result', 'rejected')->count(),
                'today' => (clone $decisions)->whereDate('created_at', today())->count(),
            ],
            'recent' => (clone $decisions)->with('fabric:id,code,yarn_type,weight')
                ->latest()->take(6)->get(),
        ]);
    }

    public function index()
    {
        return Inertia::render('Dashboard/MAN/Employee/KnittingInspector/Index', [
            'fabrics' => Fabric::with('machine', 'operator', 'salesOrder.client', 'manufacturingOrder')
                ->where('status', 'pending')->orderBy('created_at')->get(),
        ]);
    }

    public function inspection()
    {
        return $this->index();
    }

    public function history()
    {
        $query = KnittingInspection::with('fabric:id,code,yarn_type,weight')
            ->where('inspector_id', $this->staff()->id);

        if ($search = request('search')) {
            $query->whereHas('fabric', fn ($fabric) => $fabric->where('code', 'like', "%{$search}%"));
        }
        if ($from = request('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = request('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return Inertia::render('Dashboard/MAN/Employee/KnittingInspector/History', [
            'inspections' => $query->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function reports()
    {
        $decisions = KnittingInspection::where('inspector_id', $this->staff()->id);
        $total = (clone $decisions)->count();
        $approved = (clone $decisions)->where('result', 'approved')->count();

        return Inertia::render('Dashboard/MAN/Employee/KnittingInspector/Reports', [
            'stats' => [
                'total' => $total,
                'approved' => $approved,
                'returned' => (clone $decisions)->where('result', 'rejected')->count(),
                'today' => (clone $decisions)->whereDate('created_at', today())->count(),
                'approval_rate' => $total ? round(($approved / $total) * 100, 1) : 0,
            ],
            'recent' => (clone $decisions)->with('fabric:id,code,yarn_type,weight')
                ->latest()->take(20)->get(),
        ]);
    }
}
