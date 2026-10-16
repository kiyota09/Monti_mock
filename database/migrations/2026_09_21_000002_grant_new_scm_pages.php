<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill the 5 new SCM pages (dashboard, planning, purchase,
     * deliveries, analytics) for everyone who already holds explicit
     * SCM grants.
     *
     * Why: the access model treats explicit rows as the EXACT access set
     * (CheckPagePermission / sidebar / Inertia auto-grants all skip the
     * native manager shortcut once any SCM row exists). Without this,
     * previously-granted SCM users would keep seeing only the 3 old pages.
     *
     * Level mirrors the user's existing SCM ceiling: anyone holding edit
     * anywhere in SCM gets edit on the new pages, otherwise view.
     * Existing rows are never touched.
     */
    public function up(): void
    {
        $newPages = ['dashboard', 'planning', 'purchase', 'deliveries', 'analytics'];

        $userIds = DB::table('page_permissions')
            ->whereIn('module', ['SCM', 'scm', 'Scm'])
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $levels = DB::table('page_permissions')
                ->where('user_id', $userId)
                ->whereIn('module', ['SCM', 'scm', 'Scm'])
                ->pluck('permission_level')
                ->map(fn ($l) => strtolower($l ?? 'edit'));

            if ($levels->contains('disabled') && ! $levels->intersect(['view', 'edit'])->isNotEmpty()) {
                continue; // fully-disabled SCM set grants nothing — leave alone
            }

            $level = $levels->contains('edit') ? 'edit' : 'view';

            foreach ($newPages as $page) {
                $exists = DB::table('page_permissions')
                    ->where('user_id', $userId)
                    ->whereIn('module', ['SCM', 'scm', 'Scm'])
                    ->whereRaw('LOWER(page) = ?', [$page])
                    ->exists();

                if (! $exists) {
                    DB::table('page_permissions')->insert([
                        'user_id' => $userId,
                        'module' => 'SCM',
                        'page' => $page,
                        'permission_level' => $level,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('page_permissions')
            ->where('module', 'SCM')
            ->whereIn('page', ['dashboard', 'planning', 'purchase', 'deliveries', 'analytics'])
            ->delete();
    }
};
