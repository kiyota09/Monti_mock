<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Core\User;
use App\Models\Hrm\EmployeeShift;
use App\Models\Hrm\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateAttendanceData extends Command
{
    protected $signature = 'app:generate-attendance';
    protected $description = 'Generate random attendance data from September to current';

    public function handle()
    {
        $this->info('Generating attendance data...');

        $users = User::where('is_active', true)
            ->whereIn('position', ['staff', 'manager', 'trainee'])
            ->get();

        if ($users->isEmpty()) {
            $this->error('No active employees found to generate attendance for.');
            return;
        }

        $startDate = Carbon::parse('2026-09-01');
        $endDate = Carbon::now();
        
        $count = 0;
        DB::transaction(function () use ($users, $startDate, $endDate, &$count) {
            foreach ($users as $user) {
                // Ensure the user has a shift record for the period
                EmployeeShift::updateOrCreate(
                    ['user_id' => $user->id],
                    ['status' => 'approved']
                );

                $currentDate = $startDate->copy();
                while ($currentDate->lte($endDate)) {
                    // Skip weekends (Saturdays and Sundays)
                    if ($currentDate->isWeekend()) {
                        $currentDate->addDay();
                        continue;
                    }

                    $rand = rand(1, 100);
                    if ($rand <= 85) {
                        // PRESENT
                        Attendance::create([
                            'user_id' => $user->id,
                            'date' => $currentDate->toDateString(),
                            'clock_in' => '08:00:00',
                            'clock_out' => '17:00:00',
                            'status' => 'present',
                            'approved_at' => now(),
                        ]);
                    } elseif ($rand <= 95) {
                        // LATE (Present but late)
                        Attendance::create([
                            'user_id' => $user->id,
                            'date' => $currentDate->toDateString(),
                            'clock_in' => '08:30:00',
                            'clock_out' => '17:00:00',
                            'status' => 'present',
                            'late_minutes' => rand(15, 120),
                            'approved_at' => now(),
                        ]);
                    } else {
                        // ABSENT
                        Attendance::create([
                            'user_id' => $user->id,
                            'date' => $currentDate->toDateString(),
                            'status' => 'absent',
                            'approved_at' => now(),
                        ]);
                    }
                    $currentDate->addDay();
                }
                $count++;
            }
        });

        $this->info("Successfully generated attendance for $count employees from September 2026 to current.");
    }
}
