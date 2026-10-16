<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give the new Compensation page the same access each account already
     * has on comparable HRM pages: mirror the payroll grant first, then
     * the employee grant, otherwise the best level among the account's
     * other HRM rows. Accounts without any explicit HRM rows need no
     * seed (native manager/staff bypass already covers them).
     */
    public function up(): void
    {
        $rows = DB::table('page_permissions')
            ->select('user_id', 'module', 'page', 'permission_level')
            ->get();

        $byUser = [];
        foreach ($rows as $r) {
            if (strtoupper((string) $r->module) !== 'HRM') {
                continue;
            }
            $byUser[$r->user_id][] = $r;
        }

        $now = now();
        foreach ($byUser as $userId => $hrmRows) {
            foreach ($hrmRows as $r) {
                if (strtolower((string) $r->page) === 'compensation') {
                    continue 2; // already has an explicit compensation row
                }
            }

            $levelOf = function (string $page) use ($hrmRows) {
                foreach ($hrmRows as $r) {
                    if (strtolower((string) $r->page) === $page) {
                        return strtolower($r->permission_level ?? 'edit');
                    }
                }

                return null;
            };

            $level = $levelOf('payroll') ?? $levelOf('employee');
            if ($level === null) {
                $levels = array_map(fn ($r) => strtolower($r->permission_level ?? 'edit'), $hrmRows);
                $level = in_array('edit', $levels, true) ? 'edit'
                    : (in_array('view', $levels, true) ? 'view' : 'disabled');
            }

            DB::table('page_permissions')->insert([
                'user_id' => $userId,
                'module' => 'HRM',
                'page' => 'compensation',
                'permission_level' => $level,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Seeded access rows are intentionally kept on rollback so no
        // account silently loses Compensation access.
    }
};
