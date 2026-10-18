<?php

use App\Models\Crm\CrmContact;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill the new CRM "messaging" page for everyone who already holds
     * explicit CRM grants, and the partner-portal "messages" page for
     * representatives who already hold "conversations".
     *
     * Explicit permission rows are the EXACT access set, so without this
     * previously-granted users would silently miss the new pages. Level
     * mirrors the user's existing CRM ceiling (edit if any edit, else view).
     */
    public function up(): void
    {
        // ── CRM staff: grant the new "messaging" page ──────────────────────
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

            $exists = DB::table('page_permissions')
                ->where('user_id', $userId)
                ->whereIn('module', ['CRM', 'crm', 'Crm'])
                ->whereRaw('LOWER(page) = ?', ['messaging'])
                ->exists();

            if (! $exists) {
                DB::table('page_permissions')->insert([
                    'user_id' => $userId,
                    'module' => 'CRM',
                    'page' => 'messaging',
                    'permission_level' => $level,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ── Partner representatives: grant "messages" where they have
        //    "conversations" (the sibling communication page) ──────────────
        CrmContact::query()
            ->whereNotNull('access_pages')
            ->chunkById(200, function ($contacts) {
                foreach ($contacts as $contact) {
                    $pages = $contact->access_pages ?? [];
                    if (! is_array($pages) || $pages === []) {
                        continue;
                    }
                    if (in_array('conversations', $pages, true) && ! in_array('messages', $pages, true)) {
                        $pages[] = 'messages';
                        $contact->access_pages = $pages;
                        $contact->save();
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('page_permissions')
            ->where('module', 'CRM')
            ->where('page', 'messaging')
            ->delete();

        CrmContact::query()
            ->whereNotNull('access_pages')
            ->chunkById(200, function ($contacts) {
                foreach ($contacts as $contact) {
                    $pages = $contact->access_pages ?? [];
                    if (! is_array($pages)) {
                        continue;
                    }
                    $filtered = array_values(array_filter($pages, fn ($p) => $p !== 'messages'));
                    if ($filtered !== $pages) {
                        $contact->access_pages = $filtered;
                        $contact->save();
                    }
                }
            });
    }
};
