<?php

namespace Database\Seeders;

use App\Models\Core\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CEOSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if the CEO account already exists to avoid duplication
        $ceo = User::firstOrCreate(
            ['email' => 'iamceo@montierp.com'],
            [
                'name' => 'MONTI BOSS CEO',
                'password' => Hash::make('password123'), // Change to a secure password
                'role' => 'CEO',
                'position' => 'manager',
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $this->command->info('CEO account created: '.$ceo->email);
    }
}
