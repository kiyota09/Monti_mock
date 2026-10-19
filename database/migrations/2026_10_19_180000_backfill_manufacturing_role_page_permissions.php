<?php

use App\Support\ManufacturingPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Plant staff workspaces used to be gated by the single "production"
     * page. Routes now ask for role-specific pages (e.g. dyeing_lab_chemist,
     * lab_tests, history), so copy each employee's existing Production level
     * onto the role pages they already had access to.
     *
     * Existing rows always win — an explicit Disabled row is never
     * re-enabled. The migration is idempotent.
     */
    public function up(): void
    {
        $users = DB::table('users')
            ->whereRaw('UPPER(role) = ?', ['MAN'])
            ->get(['id', 'position', 'manufacturing_role', 'is_manufacturing_supervisor']);

        foreach ($users as $user) {
            $production = DB::table('page_permissions')
                ->where('user_id', $user->id)
                ->whereRaw('UPPER(module) = ?', ['MAN'])
                ->whereRaw('LOWER(page) = ?', ['production'])
                ->first();

            if (! $production) {
                continue;
            }

            $level = strtolower((string) ($production->permission_level ?? 'edit'));
            if (! in_array($level, ['view', 'edit'], true)) {
                continue;
            }

            $pages = ! empty($user->is_manufacturing_supervisor)
                ? array_keys(ManufacturingPages::OVERVIEW)
                : array_keys(ManufacturingPages::forRole($user->manufacturing_role));

            foreach ($pages as $page) {
                $exists = DB::table('page_permissions')
                    ->where('user_id', $user->id)
                    ->whereRaw('UPPER(module) = ?', ['MAN'])
                    ->whereRaw('LOWER(page) = ?', [strtolower($page)])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('page_permissions')->insert([
                    'user_id' => $user->id,
                    'module' => 'MAN',
                    'page' => $page,
                    'permission_level' => $level,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data backfill only — no safe way to distinguish copied rows from
        // grants IT later changed, so leave them in place.
    }
};
