<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class ProDashboardController extends Controller
{
    public function managerDashboard()
    {
        return Redirect::route('pro.manager.material-requests');
    }

}
