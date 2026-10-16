<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce one row per (user_id, module, page).
     *
     * Previously the permission syncs ran as non-transactional
     * delete-then-insert loops with no uniqueness guard, so concurrent
     * updates (or retries) could leave duplicate rows behind and make
     * authorization checks ambiguous. Duplicates are collapsed first
     * (keeping the most recently written row), then a unique index is added.
     */
    public function up(): void
    {
        // Collapse duplicates, keeping the latest row per key. Prefer an
        // 'edit' row when levels differ so nobody loses write access.
        $groups = DB::table('page_permissions')
            ->select('user_id', 'module', 'page', DB::raw('COUNT(*) as cnt'), DB::raw('MAX(id) as max_id'))
            ->groupBy('user_id', 'module', 'page')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $winnerId = DB::table('page_permissions')
                ->where('user_id', $group->user_id)
                ->where('module', $group->module)
                ->where('page', $group->page)
                ->orderByRaw("CASE WHEN permission_level = 'edit' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->value('id');

            DB::table('page_permissions')
                ->where('user_id', $group->user_id)
                ->where('module', $group->module)
                ->where('page', $group->page)
                ->where('id', '!=', $winnerId ?? $group->max_id)
                ->delete();
        }

        Schema::table('page_permissions', function (Blueprint $table) {
            $table->unique(['user_id', 'module', 'page'], 'page_permissions_user_module_page_unique');
        });
    }

    public function down(): void
    {
        Schema::table('page_permissions', function (Blueprint $table) {
            $table->dropUnique('page_permissions_user_module_page_unique');
        });
    }
};
