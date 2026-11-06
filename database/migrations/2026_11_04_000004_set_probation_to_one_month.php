<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')
            ->join('hrm_employment_types', 'users.hrm_employment_type_id', '=', 'hrm_employment_types.id')
            ->whereRaw('LOWER(hrm_employment_types.name) = ?', ['probationary'])
            ->select('users.id', 'users.join_date', 'users.created_at')
            ->get();

        foreach ($users as $user) {
            $start = $user->join_date ?: $user->created_at;
            $end = $start ? Carbon::parse($start)->addMonthNoOverflow()->toDateString() : null;
            DB::table('users')->where('id', $user->id)->update([
                'probation_duration_months' => 1,
                'probation_evaluation_interval_months' => 1,
                'probation_end_date' => $end,
            ]);
        }
    }

    public function down(): void
    {
        // Keep the end dates already used by evaluation records.
    }
};
