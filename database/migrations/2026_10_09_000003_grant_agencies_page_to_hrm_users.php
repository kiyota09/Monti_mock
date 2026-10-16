<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * New HRM > Agencies page: grant it to everyone who already holds a
     * usable HRM grant, so the sidebar link and routes work immediately.
     * Level mirrors their `employee` page grant (else view). Users whose
     * HRM rows are all disabled, and users with zero HRM rows, get nothing
     * (native manager/staff auto-access already covers the no-row case).
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('page_permissions')) {
            return;
        }

        $grantees = DB::table('page_permissions as p')
            ->select('p.user_id')
            ->whereIn('p.module', ['HRM', 'hrm', 'Hrm'])
            ->where(function ($q) {
                $q->whereIn('p.permission_level', ['view', 'edit'])
                    ->orWhereNull('p.permission_level');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('page_permissions as x')
                    ->whereColumn('x.user_id', 'p.user_id')
                    ->whereIn('x.module', ['HRM', 'hrm', 'Hrm'])
                    ->whereRaw('LOWER(x.page) = ?', ['agencies']);
            })
            ->groupBy('p.user_id')
            ->pluck('p.user_id');

        foreach ($grantees as $userId) {
            $employeeLevelRaw = DB::table('page_permissions')
                ->where('user_id', $userId)
                ->whereIn('module', ['HRM', 'hrm', 'Hrm'])
                ->whereRaw('LOWER(page) = ?', ['employee'])
                ->orderByDesc('id')
                ->value('permission_level');

            // Legacy NULL levels are grandfathered as 'edit' everywhere else.
            $employeeLevel = $employeeLevelRaw === null
                ? 'edit'
                : strtolower((string) $employeeLevelRaw);
            $level = in_array($employeeLevel, ['view', 'edit'], true) ? $employeeLevel : 'view';

            DB::table('page_permissions')->insert([
                'user_id' => $userId,
                'module' => 'HRM',
                'page' => 'agencies',
                'permission_level' => $level,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('page_permissions')) {
            return;
        }

        DB::table('page_permissions')
            ->whereIn('module', ['HRM', 'hrm', 'Hrm'])
            ->whereRaw('LOWER(page) = ?', ['agencies'])
            ->delete();
    }
};
