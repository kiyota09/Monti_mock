<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ❌ Removed duplicate user columns

        // Create permissions table
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Create role_permissions table
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');       // HRM, SCM, etc.
            $table->string('position');   // manager, staff
            $table->foreignId('permission_id')
                ->constrained()
                ->onDelete('cascade');
            $table->timestamps();

            $table->unique(['role', 'position', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }
};
