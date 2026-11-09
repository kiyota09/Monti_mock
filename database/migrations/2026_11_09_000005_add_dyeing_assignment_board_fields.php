<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->string('dyeing_color_group', 20)->nullable()->after('dyeing_bleaching_required');
            $table->json('dyeing_stage_processes')->nullable()->after('dyeing_color_group');
        });

        Schema::table('man_task_assignments', function (Blueprint $table) {
            $table->string('process', 10)->nullable()->after('stage');
            $table->unsignedInteger('queue_position')->nullable()->after('sequence');
        });

        Schema::create('dyeing_machine_staff_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->string('stage', 40);
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['machine_id', 'stage', 'staff_id'], 'dyeing_machine_staff_unique');
            $table->index(['machine_id', 'stage', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dyeing_machine_staff_orders');

        Schema::table('man_task_assignments', function (Blueprint $table) {
            $table->dropColumn(['process', 'queue_position']);
        });

        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->dropColumn(['dyeing_color_group', 'dyeing_stage_processes']);
        });
    }
};
