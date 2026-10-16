<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill the 5 new CRM pages (opportunities, quotations,
     * activities, cases, campaigns) for everyone who already holds
     * explicit CRM grants.
     *
     * Why: the access model treats explicit rows as the EXACT access set
     * (CheckPagePermission / sidebar / Inertia auto-grants all skip the
     * native manager shortcut once any CRM row exists). Without this,
     * previously-granted CRM users would keep seeing only the old pages.
     *
     * Level mirrors the user's existing CRM ceiling: anyone holding edit
     * anywhere in CRM gets edit on the new pages, otherwise view.
     * Existing rows are never touched.
     */
    public function up(): void
    {
        $newPages = ['opportunities', 'quotations', 'activities', 'cases', 'campaigns'];

        $userIds = DB::table('page_permissions')
            ->whereIn('module', ['CRM', 'crm', 'Crm'])
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $levels = DB::table('page_permissions')
                ->where('user_id', $userId)
                ->whereIn('module', ['CRM', 'crm', 'Crm'])
                ->pluck('permission_level')
                ->map(fn ($l) => strtolower($l ?? 'edit'));

            if ($levels->contains('disabled') && ! $levels->intersect(['view', 'edit'])->isNotEmpty()) {
                continue; // fully-disabled CRM set grants nothing — leave alone
            }

            $level = $levels->contains('edit') ? 'edit' : 'view';

            foreach ($newPages as $page) {
                $exists = DB::table('page_permissions')
                    ->where('user_id', $userId)
                    ->whereIn('module', ['CRM', 'crm', 'Crm'])
                    ->whereRaw('LOWER(page) = ?', [$page])
                    ->exists();

                if (! $exists) {
                    DB::table('page_permissions')->insert([
                        'user_id' => $userId,
                        'module' => 'CRM',
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
            ->where('module', 'CRM')
            ->whereIn('page', ['opportunities', 'quotations', 'activities', 'cases', 'campaigns'])
            ->delete();
    }
};
