<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unify page_permissions keys with the canonical page identifiers enforced
 * by routes (page.permission middleware), desktop/mobile sidebars
 * (permKey) and config/module_pages.php.
 *
 * Without this, grants stored under legacy keys (e.g. HRM.employees) never
 * match enforcement checks (HRM.employee) — pages stay hidden and URLs 403.
 */
return new class extends Migration
{
    protected array $renames = [
        // [module (any case), old page, new page]
        ['HRM', 'employees', 'employee'],
        ['HRM', 'applications', 'application'],
        ['WAR', 'index', 'warehouse'],
        ['WAR', 'rejects', 'reject'],
        ['MAN', 'rejected', 'reject'],
        ['ECO', 'inquiries', 'inquiry'],
        ['SCM', 'sales-orders', 'sales'],
        ['SCM', 'procurement-orders', 'procurement'],
        ['SCM', 'vendors', 'vendor'],
        ['WRF', 'schedule', 'scheduler'],
    ];

    public function up(): void
    {
        // 1. Normalise module codes to UPPER (frontend checks strict equality).
        foreach (DB::table('page_permissions')->select('module')->distinct()->pluck('module') as $module) {
            $upper = strtoupper((string) $module);
            if ($module !== $upper) {
                DB::table('page_permissions')->where('module', $module)->update(['module' => $upper]);
            }
        }

        // 2. Rename legacy page keys, merging into existing canonical rows.
        foreach ($this->renames as [$module, $old, $new]) {
            $rows = DB::table('page_permissions')
                ->where('module', $module)
                ->where('page', $old)
                ->get(['id', 'user_id', 'permission_level']);

            foreach ($rows as $row) {
                $target = DB::table('page_permissions')
                    ->where('user_id', $row->user_id)
                    ->where('module', $module)
                    ->where('page', $new)
                    ->first(['id', 'permission_level']);

                if ($target) {
                    // Keep the stronger level, drop the legacy duplicate.
                    $rank = ['view' => 1, 'edit' => 2];
                    $best = ($rank[strtolower($row->permission_level ?? 'edit')] ?? 2)
                        >= ($rank[strtolower($target->permission_level ?? 'edit')] ?? 2)
                        ? strtolower($row->permission_level ?? 'edit')
                        : strtolower($target->permission_level ?? 'edit');
                    DB::table('page_permissions')->where('id', $target->id)->update(['permission_level' => $best]);
                    DB::table('page_permissions')->where('id', $row->id)->delete();
                } else {
                    DB::table('page_permissions')->where('id', $row->id)->update(['page' => $new]);
                }
            }
        }
    }

    public function down(): void
    {
        // Renames are lossy when merged (levels) — no safe automatic reverse.
    }
};
