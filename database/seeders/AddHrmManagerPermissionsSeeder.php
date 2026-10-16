<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AddHrmManagerPermissionsSeeder extends Seeder
{
    public function run()
    {
        $hrmManager = DB::table('users')->where('email', 'hrm.manager@example.com')->first();

        if (!$hrmManager) {
            $this->command->error('HRM manager not found.');
            return;
        }

        $pages = ['dashboard', 'employee', 'application', 'interview', 'trainee', 'onboarding', 'access', 'payroll', 'analytics'];

        foreach ($pages as $page) {
            DB::table('page_permissions')->updateOrInsert(
                [
                    'user_id' => $hrmManager->id,
                    'module'  => 'HRM',
                    'page'    => $page,
                ],
                [
                    'permission_level' => 'edit',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]
            );
        }

        $this->command->info('HRM manager granted edit permissions for all HRM pages.');
    }
}