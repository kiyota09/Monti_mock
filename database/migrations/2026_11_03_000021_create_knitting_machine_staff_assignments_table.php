<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knitting_machine_staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->unique()->constrained('machines')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knitting_machine_staff_assignments');
    }
};
