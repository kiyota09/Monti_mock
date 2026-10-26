<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill the new CRM "Email" inbox page for every account that already
     * holds explicit CRM grants. Explicit rows are the exact access set, so
     * without this previously-granted users would not see the new page.
     */
    public function up(): void
    {
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
                continue;
            }

            $level = $levels->contains('edit') ? 'edit' : 'view';

            $exists = DB::table('page_permissions')
                ->where('user_id', $userId)
                ->whereIn('module', ['CRM', 'crm', 'Crm'])
                ->whereRaw('LOWER(page) = ?', ['email'])
                ->exists();

            if (! $exists) {
                DB::table('page_permissions')->insert([
                    'user_id' => $userId,
                    'module' => 'CRM',
                    'page' => 'email',
                    'permission_level' => $level,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('page_permissions')
            ->where('module', 'CRM')
            ->where('page', 'email')
            ->delete();
    }
};
