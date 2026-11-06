<?php

namespace App\Http\Controllers\Man\Staff;

use App\Http\Controllers\Man\Concerns\HandlesProductionGates;
use App\Models\Man\IronJob;
use App\Models\Man\SqueezerJob;
use Inertia\Inertia;

class FinishingInspectorController extends ManufacturingStaffController
{
    use HandlesProductionGates;

    public function index()
    {
        return Inertia::render('Dashboard/MAN/Employee/FinishingInspector/Dashboard', [
            'stats' => [
                'squeezer_pending' => $this->squeezerQueue()->count(),
                'iron_pending' => $this->ironQueue()->count(),
                'squeezer_jobs' => SqueezerJob::count(),
                'iron_jobs' => IronJob::count(),
            ],
        ]);
    }

    public function inspection()
    {
        return Inertia::render('Dashboard/MAN/Employee/FinishingInspector/Index', [
            'squeezerJobs' => $this->squeezerQueue()->with('fabric.salesOrder.client', 'machine', 'operator')->latest()->get(),
            'ironJobs' => $this->ironQueue()->with('squeezerJob.fabric.salesOrder.client', 'squeezerJob.machine', 'operator')->latest()->get(),
        ]);
    }

    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/FinishingInspector/History', [
            'squeezerJobs' => SqueezerJob::with('fabric:id,code,status', 'operator:id,name')->latest()->paginate(15, ['*'], 'squeezer_page'),
            'ironJobs' => IronJob::with('squeezerJob.fabric:id,code,status', 'operator:id,name')->latest()->paginate(15, ['*'], 'iron_page'),
        ]);
    }

    public function reports()
    {
        return Inertia::render('Dashboard/MAN/Employee/FinishingInspector/Reports', [
            'stats' => [
                'squeezer_jobs' => SqueezerJob::count(),
                'iron_jobs' => IronJob::count(),
                'squeezer_pending' => $this->squeezerQueue()->count(),
                'iron_pending' => $this->ironQueue()->count(),
            ],
        ]);
    }

    private function squeezerQueue()
    {
        return SqueezerJob::whereHas('fabric', fn ($fabric) => $fabric->where('status', 'squeezer'));
    }

    private function ironQueue()
    {
        return IronJob::whereHas('squeezerJob.fabric', fn ($fabric) => $fabric->where('status', 'iron'));
    }
}
