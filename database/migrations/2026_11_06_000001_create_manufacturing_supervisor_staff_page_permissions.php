<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('man_supervisor_staff_page_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('manufacturing_role', 60);
            $table->string('page', 80);
            $table->string('permission_level', 20)->default('disabled');
            $table->timestamps();

            $table->unique(
                ['supervisor_user_id', 'manufacturing_role', 'page'],
                'man_supervisor_staff_page_unique'
            );
            $table->index(['supervisor_user_id', 'manufacturing_role'], 'man_supervisor_staff_role_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('man_supervisor_staff_page_permissions');
    }
};
