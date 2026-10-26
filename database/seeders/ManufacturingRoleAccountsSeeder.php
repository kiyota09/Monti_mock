<?php

namespace Database\Seeders;

use App\Support\ManufacturingRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ManufacturingRoleAccountsSeeder extends Seeder
{
    private const TARGET_PER_ROLE = 6;

    public function run(): void
    {
        $password = Hash::make('password');
        $assignedBy = DB::table('users')->where('role', 'CEO')->value('id');
        $now = now();

        $firstNames = [
            'Miguel', 'Andrea', 'Rafael', 'Bea', 'Carlo', 'Diana', 'Enrico', 'Faith',
            'Gabriel', 'Hazel', 'Ivan', 'Jasmine', 'Kevin', 'Liza', 'Marco', 'Nina',
            'Oscar', 'Patricia', 'Quennie', 'Rico', 'Sofia', 'Tomas', 'Ursula', 'Victor',
        ];

        $lastNames = [
            'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres',
            'Villanueva', 'Aquino', 'Ramos', 'Castillo', 'Flores', 'Gonzales', 'Navarro',
            'Pascual', 'Rivera', 'Salazar', 'Domingo', 'Lim', 'Tan', 'Sy', 'Co', 'Chua',
        ];

        $roleIndex = 0;

        foreach (ManufacturingRoles::allRoles() as $role) {
            $existing = DB::table('users')->where('manufacturing_role', $role)->count();
            $needed = max(0, self::TARGET_PER_ROLE - $existing);
            $number = DB::table('users')->where('email', 'like', "man.{$role}.%@example.com")->count();

            for ($i = 1; $i <= $needed; $i++) {
                $number++;

                do {
                    $email = "man.{$role}.{$number}@example.com";
                    $number++;
                } while (DB::table('users')->where('email', $email)->exists());

                $nameIndex = ($roleIndex * self::TARGET_PER_ROLE + $i) % count($firstNames);
                $surnameIndex = ($roleIndex * 5 + $i * 3) % count($lastNames);
                $firstName = $firstNames[$nameIndex];
                $lastName = $lastNames[$surnameIndex];

                DB::table('users')->insert([
                    'name' => "{$firstName} {$lastName}",
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'email_verified_at' => $now,
                    'password' => $password,
                    'must_change_password' => 0,
                    'role' => 'MAN',
                    'hrm_role_id' => 4,
                    'hrm_department_id' => 4,
                    'position' => 'staff',
                    'manufacturing_role' => $role,
                    'manufacturing_role_assigned_by' => $assignedBy,
                    'is_active' => 1,
                    'timezone' => 'GMT+8',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $roleIndex++;
        }
    }
}
