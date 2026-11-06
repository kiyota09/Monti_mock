<?php

use App\Support\ManufacturingPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')
            ->whereIn('manufacturing_role', [
                'finishing_squeezer', 'finishing_ironing', 'finishing_hydro', 'finishing_tubetex',
                'finishing_packaging', 'finishing_inspector', 'checker_quality',
            ])->get(['id', 'manufacturing_role']);

        foreach ($users as $user) {
            $production = DB::table('page_permissions')
                ->where('user_id', $user->id)
                ->whereRaw('UPPER(module) = ?', ['MAN'])
                ->whereRaw('LOWER(page) = ?', ['production'])
                ->first();
            $level = strtolower((string) ($production->permission_level ?? ''));
            if (! in_array($level, ['view', 'edit'], true)) {
                continue;
            }

            foreach (array_keys(ManufacturingPages::forRole($user->manufacturing_role)) as $page) {
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
        // Keep page permissions so IT changes are not erased on rollback.
    }
};
