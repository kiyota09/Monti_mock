<?php

namespace App\Http\Controllers\Fin;

use App\Http\Controllers\Controller;
use App\Models\Fin\FinExpense;
use App\Models\Hrm\Payroll;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollReviewController extends Controller
{
    /**
     * Get the detailed payslip for a specific expense.
     */
    public function show(Request $request, $id)
    {
        $expense = FinExpense::findOrFail($id);
        
        if (!$expense->payroll_id) {
            return response()->json(['error' => 'This expense is not linked to a payroll record.'], 404);
        }

        $payroll = Payroll::findOrFail($expense->payroll_id);

        return response()->json([
            'expense' => $expense,
            'payroll' => $payroll,
        ]);
    }

    /**
     * Get the list of payroll expenses for a specific date.
     */
    public function getByDate(Request $request)
    {
        $date = $request->query('date');
        if (!$date) {
            return response()->json(['error' => 'Date is required.'], 400);
        }

        $expenses = FinExpense::where('expense_date', $date)
            ->where('category', 'Payroll')
            ->with('payroll') // Assuming relation is defined
            ->get();

        return response()->json($expenses);
    }
}
