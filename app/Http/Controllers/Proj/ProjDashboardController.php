<?php

namespace App\Http\Controllers\Proj;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ProjDashboardController extends Controller
{
    public function managerDashboard()
    {
        return Inertia::render('Dashboard/PROJ/Manager/index', [
            'user' => auth()->user(),
            'stats' => [
                'activeProjects' => 0,
                'delayedProjects' => 0,
                'budgetUtilized' => '0%',
            ],
        ]);
    }

    public function staffDashboard()
    {
        return Inertia::render('Dashboard/PROJ/Employee/index', [
            'user' => auth()->user(),
            'stats' => [
                'activeProjects' => 0,
                'delayedProjects' => 0,
                'budgetUtilized' => '0%',
            ],
        ]);
    }
}
