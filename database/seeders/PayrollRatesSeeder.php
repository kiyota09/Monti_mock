<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PayrollSet;
use App\Models\GovernmentContributionRate;

class PayrollRatesSeeder extends Seeder
{
    public function run()
    {
        // Default payroll sets
        PayrollSet::create([
            'name' => 'Default Staff',
            'employee_type' => 'staff',
            'base_salary' => 15000,
            'ot_rate' => 1.25,
            'night_rate' => 1.10,
            'late_rate_per_minute' => 5.00,
            'sunday_rate' => 1.30,
            'is_active' => true,
        ]);

        PayrollSet::create([
            'name' => 'Default Manager',
            'employee_type' => 'manager',
            'base_salary' => 30000,
            'ot_rate' => 1.25,
            'night_rate' => 1.10,
            'late_rate_per_minute' => 10.00,
            'sunday_rate' => 1.30,
            'is_active' => true,
        ]);

        // SSS brackets (2024)
        $sssBrackets = [
            [4000, 4500, 4.5], [4500, 5000, 4.5], [5000, 5500, 4.5], [5500, 6000, 4.5],
            [6000, 6500, 4.5], [6500, 7000, 4.5], [7000, 7500, 4.5], [7500, 8000, 4.5],
            [8000, 8500, 4.5], [8500, 9000, 4.5], [9000, 9500, 4.5], [9500, 10000, 4.5],
            [10000, 10500, 4.5], [10500, 11000, 4.5], [11000, 11500, 4.5], [11500, 12000, 4.5],
            [12000, 12500, 4.5], [12500, 13000, 4.5], [13000, 13500, 4.5], [13500, 14000, 4.5],
            [14000, 14500, 4.5], [14500, 15000, 4.5], [15000, 15500, 4.5], [15500, 16000, 4.5],
            [16000, 16500, 4.5], [16500, 17000, 4.5], [17000, 17500, 4.5], [17500, 18000, 4.5],
            [18000, 18500, 4.5], [18500, 19000, 4.5], [19000, 19500, 4.5], [19500, 20000, 4.5],
            [20000, 20500, 4.5], [20500, 21000, 4.5], [21000, 21500, 4.5], [21500, 22000, 4.5],
            [22000, 22500, 4.5], [22500, 23000, 4.5], [23000, 23500, 4.5], [23500, 24000, 4.5],
            [24000, 24500, 4.5], [24500, 25000, 4.5], [25000, 25500, 4.5], [25500, 26000, 4.5],
            [26000, 26500, 4.5], [26500, 27000, 4.5], [27000, 27500, 4.5], [27500, 28000, 4.5],
            [28000, 28500, 4.5], [28500, 29000, 4.5], [29000, 29500, 4.5], [29500, 30000, 4.5],
        ];
        foreach ($sssBrackets as $b) {
            GovernmentContributionRate::create([
                'type' => 'sss',
                'min_compensation' => $b[0],
                'max_compensation' => $b[1],
                'percentage' => $b[2],
                'is_active' => true,
            ]);
        }

        // PhilHealth: 5% total (2.5% employee)
        GovernmentContributionRate::create([
            'type' => 'philhealth',
            'percentage' => 2.5,
            'min_compensation' => 10000,
            'max_compensation' => 100000,
            'is_active' => true,
        ]);

        // Pag-IBIG: 2% max 200
        GovernmentContributionRate::create([
            'type' => 'pagibig',
            'percentage' => 2,
            'fixed_amount' => 200,
            'is_active' => true,
        ]);

        // Tax brackets (TRAIN) can be added similarly
    }
}