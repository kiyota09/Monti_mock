<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\PayrollSet;
use App\Models\Hrm\GovernmentContributionRate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollRatesController extends Controller
{
    public function index()
    {
        $payrollSets = PayrollSet::orderBy('employee_type')->get();
        $sssRates = GovernmentContributionRate::ofType('sss')->get();
        $philhealthRates = GovernmentContributionRate::ofType('philhealth')->get();
        $pagibigRates = GovernmentContributionRate::ofType('pagibig')->get();
        $taxRates = GovernmentContributionRate::ofType('tax')->get();

        return Inertia::render('Dashboard/HRM_NEW/PayrollRates', [
            'payrollSets'      => $payrollSets,
            'sssRates'         => $sssRates,
            'philhealthRates'  => $philhealthRates,
            'pagibigRates'     => $pagibigRates,
            'taxRates'         => $taxRates,
        ]);
    }

    public function storePayrollSet(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string',
            'employee_type'         => 'required|in:staff,manager,trainee',
            'base_salary'           => 'required|numeric',
            'ot_rate'               => 'required|numeric',
            'night_rate'            => 'required|numeric',
            'late_rate_per_minute'  => 'required|numeric',
            'sunday_rate'           => 'required|numeric',
        ]);

        PayrollSet::create($validated + ['is_active' => true]);

        return back()->with('message', 'Payroll set created.');
    }

    public function updatePayrollSet(Request $request, PayrollSet $payrollSet)
    {
        $validated = $request->validate([
            'name'                  => 'required|string',
            'base_salary'           => 'required|numeric',
            'ot_rate'               => 'required|numeric',
            'night_rate'            => 'required|numeric',
            'late_rate_per_minute'  => 'required|numeric',
            'sunday_rate'           => 'required|numeric',
        ]);

        $payrollSet->update($validated);

        return back()->with('message', 'Payroll set updated.');
    }

    public function togglePayrollSet(PayrollSet $payrollSet)
    {
        $payrollSet->update(['is_active' => !$payrollSet->is_active]);
        return back();
    }

    public function storeContributionRate(Request $request)
    {
        $validated = $request->validate([
            'type'              => 'required|in:sss,philhealth,pagibig,tax',
            'bracket_name'      => 'nullable|string',
            'min_compensation'  => 'nullable|numeric',
            'max_compensation'  => 'nullable|numeric',
            'employee_share'    => 'nullable|numeric',
            'employer_share'    => 'nullable|numeric',
            'fixed_amount'      => 'nullable|numeric',
            'percentage'        => 'nullable|numeric',
        ]);

        GovernmentContributionRate::create($validated + ['is_active' => true]);

        return back()->with('message', 'Rate added.');
    }

    public function updateContributionRate(Request $request, GovernmentContributionRate $rate)
    {
        $validated = $request->validate([
            'bracket_name'      => 'nullable|string',
            'min_compensation'  => 'nullable|numeric',
            'max_compensation'  => 'nullable|numeric',
            'employee_share'    => 'nullable|numeric',
            'employer_share'    => 'nullable|numeric',
            'fixed_amount'      => 'nullable|numeric',
            'percentage'        => 'nullable|numeric',
        ]);

        $rate->update($validated);

        return back()->with('message', 'Rate updated.');
    }

    public function toggleContributionRate(GovernmentContributionRate $rate)
    {
        $rate->update(['is_active' => !$rate->is_active]);
        return back();
    }
}