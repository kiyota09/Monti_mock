<?php

namespace Database\Seeders;

use App\Support\ManufacturingPages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class FinishingInspectorAccountSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'finishing.inspector@example.com';
        if (DB::table('users')->where('email', $email)->exists()) {
            throw new RuntimeException("An account already exists for {$email}; no account data was changed.");
        }

        $now = now();
        $assignedBy = DB::table('users')->where('role', 'CEO')->value('id');

        DB::transaction(function () use ($email, $now, $assignedBy): void {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Finishing Shrinkage Inspector',
                'first_name' => 'Finishing',
                'last_name' => 'Inspector',
                'email' => $email,
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'must_change_password' => 1,
                'role' => 'MAN',
                'position' => 'staff',
                'hrm_role_id' => 4,
                'hrm_department_id' => 4,
                'manufacturing_role' => 'finishing_inspector',
                'manufacturing_role_assigned_by' => $assignedBy,
                'is_active' => 1,
                'timezone' => 'GMT+8',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('users')->where('id', $userId)->update([
                'employee_id' => 'MONTI-'.now()->format('Y').'-MAN-'.str_pad((string) $userId, 5, '0', STR_PAD_LEFT),
            ]);

            foreach (array_keys(ManufacturingPages::forRole('finishing_inspector')) as $page) {
                DB::table('page_permissions')->updateOrInsert(
                    ['user_id' => $userId, 'module' => 'MAN', 'page' => $page],
                    ['permission_level' => 'edit', 'created_at' => $now, 'updated_at' => $now],
                );
            }
        });
    }
}
