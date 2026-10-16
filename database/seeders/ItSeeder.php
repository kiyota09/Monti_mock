<?php

namespace Database\Seeders;

use App\Models\Core\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * IT module seeder — production-safe.
 *
 * Creates ONLY the essential IT department login accounts. All operational
 * data (tickets, assets, systems, knowledge articles, changes) must be
 * entered live through the IT module UI so the database holds real data.
 */
class ItSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'it.manager@montierp.com'],
            [
                'name' => 'IT Manager',
                'role' => 'IT',
                'position' => 'manager',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'it.staff@montierp.com'],
            [
                'name' => 'IT Support Staff',
                'role' => 'IT',
                'position' => 'staff',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
    }
}
