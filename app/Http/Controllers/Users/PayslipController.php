<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class PayslipController extends Controller
{
    public function payslip()
    {
        return Inertia::render('Dashboard/USERS/payslip');
    }
}
