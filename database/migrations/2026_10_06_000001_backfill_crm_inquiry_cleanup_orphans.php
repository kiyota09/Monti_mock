<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Inquiries moved ECO → CRM, but existing CRM grant holders never
     * received the page: the move migration only rewrote ECO/inquiry rows,
     * and the new-pages backfill predates the move. Since explicit rows are
     * the EXACT access set, those users' sidebars kept hiding Inquiries and
     * the page 403'd even after IT granted the CRM module.
     *
     * This backfills CRM/inquiry for everyone holding usable CRM grants
     * (mirroring 2026_09_21_000010_grant_new_crm_pages ceiling logic) and
     * drops orphaned CRM rows for pages with no route/sidebar entry
     * (socials — removed; access — never had one).
     */
    public function up(): void
    {
        // 1. Drop orphans that can never render or authorize.
        DB::table('page_permissions')
            ->whereIn('module', ['CRM', 'crm', 'Crm'])
            ->whereIn('page', ['socials', 'access'])
            ->delete();

        // 2. Backfill inquiry for holders of usable CRM grants.
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

            $exists = DB::table('page_permissions')
                ->where('user_id', $userId)
                ->whereIn('module', ['CRM', 'crm', 'Crm'])
                ->whereRaw('LOWER(page) = ?', ['inquiry'])
                ->exists();

            if ($exists) {
                continue;
            }

            $level = $levels->contains('edit') ? 'edit' : 'view';

            DB::table('page_permissions')->insert([
                'user_id' => $userId,
                'module' => 'CRM',
                'page' => 'inquiry',
                'permission_level' => $level,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Backfilled inquiry rows are indistinguishable from IT-granted ones —
        // no safe automatic reverse. Orphan deletes stay deleted.
    }
};
