<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Original roles – no ADMIN
            $table->enum('role', [
                'HRM', 'SCM', 'FIN', 'MAN',
                'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'PRO', 'PROJ', 'IT', 'CEO',
            ])->default('HRM');

            // UPDATED: Added the new hierarchy ranks and pipeline positions
            $table->enum('position', [
                'trainee', 'onboarding', 'staff', 'supervisor', 'manager', 'general_manager', 'secretary',
            ])->default('staff');

            $table->rememberToken();
            $table->timestamps();

            // New employee columns – nullable
            $table->string('employee_id')->nullable()->unique();
            $table->string('department')->nullable();
            $table->date('join_date')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable(); // ✅ Fixed here
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Default admin user
        DB::table('users')->insert([
            'name' => 'Boss CEO MontiTextile',
            'email' => 'iamceo@montierp.com',
            'password' => bcrypt('password'),
            'role' => 'CEO',
            'position' => 'manager',
            'email_verified_at' => now(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
