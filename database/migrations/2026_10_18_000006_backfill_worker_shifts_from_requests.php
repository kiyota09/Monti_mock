<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Deployed workers already had a shift chosen at account creation, but it
     * was only stored on the manpower request. Backfill hrm_shift_worker for
     * currently deployed workers whose request shift matches a shift
     * definition, so the Shift Management page shows it.
     */
    public function up(): void
    {
        $now = now();

        $rows = DB::table('manpower_assignments as ma')
            ->join('manpower_requests as mr', 'mr.id', '=', 'ma.request_id')
            ->join('workers as w', 'w.id', '=', 'ma.worker_id')
            ->join('hrm_shifts as s', 's.name', '=', 'mr.shift')
            ->whereNull('ma.released_at')
            ->where('w.status', 'Deployed')
            ->whereNotNull('mr.shift')
            ->select('ma.worker_id', 's.id as shift_id', 'mr.start_date')
            ->get();

        foreach ($rows as $r) {
            DB::table('hrm_shift_worker')->updateOrInsert(
                ['shift_id' => $r->shift_id, 'worker_id' => $r->worker_id],
                [
                    'effective_date' => $r->start_date,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        // Data backfill — intentionally not reversed.
    }
};
