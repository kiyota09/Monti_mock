<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $userIds = DB::table('users')
            ->whereRaw('UPPER(role) = ?', ['MAN'])
            ->where('manufacturing_role', 'dyeing_lab_chemist')
            ->pluck('id');

        foreach ($userIds as $userId) {
            $permissions = DB::table('page_permissions')
                ->where('user_id', $userId)
                ->whereRaw('UPPER(module) = ?', ['MAN'])
                ->get(['page', 'permission_level']);

            // Do not add one explicit page grant to a native account with no
            // explicit MAN grants: that would turn on strict page filtering.
            if ($permissions->isEmpty()) {
                continue;
            }

            if ($permissions->contains(fn ($permission) => strtolower((string) $permission->page) === 'pantones')) {
                continue;
            }

            $hasAnyEnabledPage = $permissions->contains(fn ($permission) => in_array(
                strtolower((string) ($permission->permission_level ?? 'edit')),
                ['view', 'edit'],
                true
            ));

            if (! $hasAnyEnabledPage) {
                continue;
            }

            DB::table('page_permissions')->insert([
                'user_id' => $userId,
                'module' => 'MAN',
                'page' => 'pantones',
                'permission_level' => 'view',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('page_permissions')
            ->whereRaw('UPPER(module) = ?', ['MAN'])
            ->whereRaw('LOWER(page) = ?', ['pantones'])
            ->delete();
    }
};
