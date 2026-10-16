<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Truncate the users table to start fresh (optional)
        // DB::table('users')->truncate();

        // Default password for all seeded accounts
        $defaultPassword = Hash::make('password');

        // --------------------------------------------
        // 1. CEO account (already exists, but we'll update or create if missing)
        // --------------------------------------------
        $ceo = DB::table('users')->updateOrInsert(
            ['email' => 'iamceo@montierp.com'],
            [
                'name' => 'Boss CEO MontiTextile',
                'email' => 'iamceo@montierp.com',
                'password' => Hash::make('password'), // or keep existing hash
                'role' => 'CEO',
                'position' => 'manager',
                'email_verified_at' => now(),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $ceoId = DB::table('users')->where('email', 'iamceo@montierp.com')->value('id');

        // --------------------------------------------
        // 2. Define all modules (except CEO)
        // --------------------------------------------
        $modules = ['HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'PRO', 'PROJ', 'IT', 'LOG'];

        // For each module, create a manager and a staff
        foreach ($modules as $module) {
            // Manager
            DB::table('users')->updateOrInsert(
                ['email' => strtolower($module) . '.manager@example.com'],
                [
                    'name' => ucfirst(strtolower($module)) . ' Manager',
                    'email' => strtolower($module) . '.manager@example.com',
                    'password' => $defaultPassword,
                    'role' => $module,
                    'position' => 'manager',
                    'email_verified_at' => now(),
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Staff
            DB::table('users')->updateOrInsert(
                ['email' => strtolower($module) . '.staff@example.com'],
                [
                    'name' => ucfirst(strtolower($module)) . ' Staff',
                    'email' => strtolower($module) . '.staff@example.com',
                    'password' => $defaultPassword,
                    'role' => $module,
                    'position' => 'staff',
                    'email_verified_at' => now(),
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // --------------------------------------------
        // 3. Additional roles for Manufacturing (MAN)
        // --------------------------------------------
        $manufacturingRoles = [
            'knitting_yarn'      => 'Knitting Yarn Staff',
            'dyeing_color'       => 'Dyeing Color Staff',
            'dyeing_fabric_softener' => 'Dyeing Fabric Softener Staff',
            'dyeing_squeezer'    => 'Dyeing Squeezer Staff',
            'dyeing_ironing'     => 'Dyeing Ironing Staff',
            'dyeing_packaging'   => 'Dyeing Packaging Staff',
            'maintenance_checker'=> 'Maintenance Checker Staff',
            'checker_quality'    => 'Checker Quality Staff',
        ];

        foreach ($manufacturingRoles as $roleKey => $roleName) {
            DB::table('users')->updateOrInsert(
                ['email' => $roleKey . '@example.com'],
                [
                    'name' => $roleName,
                    'email' => $roleKey . '@example.com',
                    'password' => $defaultPassword,
                    'role' => 'MAN',
                    'position' => 'staff',
                    'manufacturing_role' => $roleKey,
                    'manufacturing_role_assigned_by' => $ceoId,
                    'email_verified_at' => now(),
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Manufacturing supervisor (for MAN module)
        DB::table('users')->updateOrInsert(
            ['email' => 'man.supervisor@example.com'],
            [
                'name' => 'Manufacturing Supervisor',
                'email' => 'man.supervisor@example.com',
                'password' => $defaultPassword,
                'role' => 'MAN',
                'position' => 'staff', // supervisors are 'staff' but with is_manufacturing_supervisor = 1
                'is_manufacturing_supervisor' => 1,
                'supervisor_department' => 'knitting', // or dyeing/maintenance
                'email_verified_at' => now(),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // --------------------------------------------
        // 4. Additional roles for Logistics (LOG)
        // --------------------------------------------
        // Driver
        DB::table('users')->updateOrInsert(
            ['email' => 'log.driver@example.com'],
            [
                'name' => 'Logistics Driver',
                'email' => 'log.driver@example.com',
                'password' => $defaultPassword,
                'role' => 'LOG',
                'position' => 'staff',
                'log_role' => 'driver',
                'email_verified_at' => now(),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Conductor
        DB::table('users')->updateOrInsert(
            ['email' => 'log.conductor@example.com'],
            [
                'name' => 'Logistics Conductor',
                'email' => 'log.conductor@example.com',
                'password' => $defaultPassword,
                'role' => 'LOG',
                'position' => 'staff',
                'log_role' => 'conductor',
                'email_verified_at' => now(),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // --------------------------------------------
        // 5. Secretary and Special Officer (often used in HRM and CEO)
        // --------------------------------------------
        // Secretary (can be assigned to HRM or CEO; we'll put HRM)
        DB::table('users')->updateOrInsert(
            ['email' => 'secretary@example.com'],
            [
                'name' => 'Executive Secretary',
                'email' => 'secretary@example.com',
                'password' => $defaultPassword,
                'role' => 'HRM', // secretaries often belong to HRM but can have cross-module access via CEO
                'position' => 'secretary',
                'email_verified_at' => now(),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Special Officer (can be for any module; we'll assign to SCM as an example)
        DB::table('users')->updateOrInsert(
            ['email' => 'gm@example.com'],
            [
                'name' => 'Special Officer',
                'email' => 'gm@example.com',
                'password' => $defaultPassword,
                'role' => 'SCM',
                'position' => 'special_officer',
                'email_verified_at' => now(),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // --------------------------------------------
        // 6. Trainees for various modules (optional)
        // --------------------------------------------
        $traineeModules = ['HRM', 'FIN', 'MAN', 'CRM'];
        foreach ($traineeModules as $mod) {
            DB::table('users')->updateOrInsert(
                ['email' => strtolower($mod) . '.trainee@example.com'],
                [
                    'name' => ucfirst(strtolower($mod)) . ' Trainee',
                    'email' => strtolower($mod) . '.trainee@example.com',
                    'password' => $defaultPassword,
                    'role' => $mod,
                    'position' => 'trainee',
                    'email_verified_at' => now(),
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Users table seeded successfully.');
    }
}