<?php

namespace App\Http\Controllers\Man\Staff;

use Inertia\Inertia;

class FinishingStationController extends ManufacturingStaffController
{
    public function dashboard()
    {
        return Inertia::render('Dashboard/MAN/Employee/FinishingStation/Dashboard', [
            'stationLabel' => $this->stationLabel(),
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function hydro()
    {
        return $this->workspace('Finishing Hydro');
    }

    public function tubetex()
    {
        return $this->workspace('Finishing Tubetex');
    }

    private function workspace(string $label)
    {
        return Inertia::render('Dashboard/MAN/Employee/FinishingStation/Index', [
            'stationLabel' => $label,
        ]);
    }

    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/FinishingStation/History', [
            'stationLabel' => $this->stationLabel(),
        ]);
    }

    public function reports()
    {
        return Inertia::render('Dashboard/MAN/Employee/FinishingStation/Reports', [
            'stationLabel' => $this->stationLabel(),
        ]);
    }

    private function stationLabel(): string
    {
        return $this->staff()->manufacturing_role === 'finishing_hydro'
            ? 'Finishing Hydro'
            : 'Finishing Tubetex';
    }

    private function routePrefix(): string
    {
        return $this->staff()->manufacturing_role === 'finishing_hydro'
            ? 'man.staff.finishing-hydro'
            : 'man.staff.finishing-tubetex';
    }
}
