<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')->where('manufacturing_role', 'dyeing_lab_chemist')->pluck('id');

        foreach ($users as $userId) {
            $production = DB::table('page_permissions')
                ->where('user_id', $userId)
                ->whereRaw('UPPER(module) = ?', ['MAN'])
                ->whereRaw('LOWER(page) = ?', ['production'])
                ->first();
            $level = strtolower((string) ($production->permission_level ?? ''));
            if (! in_array($level, ['view', 'edit'], true)) {
                continue;
            }

            $exists = DB::table('page_permissions')->where('user_id', $userId)
                ->whereRaw('UPPER(module) = ?', ['MAN'])
                ->whereRaw('LOWER(page) = ?', ['knit_fabric_requests'])->exists();
            if (! $exists) {
                DB::table('page_permissions')->insert([
                    'user_id' => $userId,
                    'module' => 'MAN',
                    'page' => 'knit_fabric_requests',
                    'permission_level' => $level,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep IT permission changes when rolling back the application code.
    }
};
