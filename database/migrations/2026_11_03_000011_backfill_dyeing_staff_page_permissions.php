<?php

use App\Support\ManufacturingPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')
            ->whereIn('manufacturing_role', ['dyeing_color', 'dyeing_bleaching', 'dyeing_pre_treatment', 'dyeing_treatment', 'dyeing_lab_chemist'])
            ->get(['id', 'manufacturing_role']);

        foreach ($users as $user) {
            $pages = array_keys(ManufacturingPages::forRole($user->manufacturing_role));
            $permission = DB::table('page_permissions')
                ->where('user_id', $user->id)
                ->whereRaw('UPPER(module) = ?', ['MAN'])
                ->whereRaw('LOWER(page) = ?', ['production'])
                ->first();

            $level = strtolower((string) ($permission->permission_level ?? ''));
            if (! in_array($level, ['view', 'edit'], true)) {
                continue;
            }

            foreach ($pages as $page) {
                $exists = DB::table('page_permissions')->where('user_id', $user->id)
                    ->whereRaw('UPPER(module) = ?', ['MAN'])
                    ->whereRaw('LOWER(page) = ?', [strtolower($page)])->exists();
                if (! $exists) {
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
    }

    public function down(): void
    {
        // Keep permission rows in place so later IT changes are not lost.
    }
};
