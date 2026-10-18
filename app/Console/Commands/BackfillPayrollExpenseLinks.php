<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Fin\FinExpense;
use App\Models\Hrm\Payroll;
use Illuminate\Support\Facades\DB;

class BackfillPayrollExpenseLinks extends Command
{
    protected $signature = 'app:backfill-payroll-expenses';
    protected $description = 'Link existing Payroll expenses to their original Payroll records';

    public function handle()
    {
        $this->info('Starting backfill of payroll expense links...');
        
        $expenses = FinExpense::where('category', 'Payroll')
            ->whereNull('payroll_id')
            ->get();

        $count = 0;
        foreach ($expenses as $expense) {
            // Description format: "Payroll approval for [Name] ([Period])"
            // Example: "Payroll approval for Sammy Games (Oct 1–15, 2026)"
            if (preg_match('/Payroll approval for (.+?) \((.+?)\)/', $expense->description, $matches)) {
                $employeeName = $matches[1];
                $period = $matches[2];

                $payroll = Payroll::where('employee_name', $employeeName)
                    ->where('period', $period)
                    ->first();

                if ($payroll) {
                    $expense->update(['payroll_id' => $payroll->id]);
                    $count++;
                }
            }
        }

        $this->info("Successfully linked $count out of " . $expenses->count() . " payroll expenses.");
    }
}
