<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddHrmManagerPermissionsSeeder extends Seeder
{
    /**
     * Canonical HRM page set. This MUST mirror the pages the HRM sidebar can
     * render (resources/js/Layouts/modules/SidebarHrm.vue) and the auto-grant
     * map in App\Http\Middleware\HandleInertiaRequests::getModulePages('HRM').
     *
     * Historically these pages were granted by one-off data migrations that
     * copied permissions from users who already existed when the migration
     * ran. On a fresh `migrate:refresh` there are no users yet, so those
     * migrations inserted nothing and the Agency group (and the other newer
     * pages) disappeared from the sidebar. Seeding the full set after users
     * are created makes the working sidebar reproducible from an empty DB.
     */
    public const HRM_PAGES = [
        'dashboard',
        'employee',
        'workforce',
        'recruitment',
        'application',
        'interview',
        'onboarding',
        'onboarding_templates',
        'organization',
        'performance',
        'training',
        'attendance',
        'leave',
        'archive',
        'trainee',
        'payroll',
        'compensation',
        'analytics',
        'agencies',
        'workers',
        'requests',
        'agency_attendance',
        'agency_billing',
    ];

    public function run(): void
    {
        if (! Schema::hasTable('page_permissions') || ! Schema::hasTable('users')) {
            return;
        }

        $userIds = DB::table('users')
            ->whereIn('role', ['HRM', 'hrm', 'Hrm'])
            ->whereIn('position', ['manager', 'secretary', 'special_officer'])
            ->pluck('id');

        $granted = 0;

        foreach ($userIds as $userId) {
            foreach (self::HRM_PAGES as $page) {
                // Never overwrite an explicit level IT already set (view /
                // disabled) — only backfill pages that have no row at all.
                $exists = DB::table('page_permissions')
                    ->where('user_id', $userId)
                    ->whereIn('module', ['HRM', 'hrm', 'Hrm'])
                    ->whereRaw('LOWER(page) = ?', [strtolower($page)])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('page_permissions')->insert([
                    'user_id'          => $userId,
                    'module'           => 'HRM',
                    'page'             => $page,
                    'permission_level' => 'edit',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                $granted++;
            }
        }

        if ($this->command) {
            $this->command->info("HRM page permissions seeded: {$granted} rows across {$userIds->count()} account(s).");
        }
    }
}
