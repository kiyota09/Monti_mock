<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\ConductorReport;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index()
    {
        $reports = ConductorReport::with(['delivery', 'conductor.user'])->latest()->get();
        return Inertia::render('Dashboard/Logistics/Report', ['reports' => $reports]);
    }
}