<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed department-aligned positions so every department has selectable
 * positions (Manager / Supervisor / Staff). Previously only Human Resources
 * (DEP-HRM) had positions, leaving every other department's position
 * dropdown empty. Re-runnable: existing codes are skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $departments = DB::table('hrm_departments')
            ->whereNull('archived_at')
            ->select('id', 'code', 'name')
            ->get();

        foreach ($departments as $dept) {
            $prefix = strtoupper((string) preg_replace('/^DEP-/i', '', (string) ($dept->code ?: $dept->name)));
            $prefix = substr(preg_replace('/[^A-Z]/', '', $prefix) ?: 'GEN', 0, 8);

            $trio = [
                ["POS-{$prefix}-MGR", "{$dept->name} Manager", 2, 'Managerial'],
                ["POS-{$prefix}-SUP", "{$dept->name} Supervisor", 3, 'Supervisory'],
                ["POS-{$prefix}-STAFF", "{$dept->name} Staff", 3, 'Individual Contributor'],
            ];

            foreach ($trio as [$code, $name, $level, $mlevel]) {
                if (DB::table('hrm_positions')->where('code', $code)->exists()) {
                    continue;
                }
                DB::table('hrm_positions')->insert([
                    'code' => $code,
                    'name' => $name,
                    'department_id' => $dept->id,
                    'management_level' => $mlevel,
                    'organization_level' => $level,
                    'approved_headcount' => 5,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Only remove rows this seeder owns (generated trio codes — the
        // original POS-001..008 seeds never match these patterns).
        DB::table('hrm_positions')
            ->where(fn ($q) => $q->where('code', 'like', 'POS-%-MGR')
                ->orWhere('code', 'like', 'POS-%-SUP')
                ->orWhere('code', 'like', 'POS-%-STAFF'))
            ->delete();
    }
};
