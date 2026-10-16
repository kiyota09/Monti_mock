<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Knitting Mechanic role (knitting department):
 * - machine setup / style changeover sheets, calibration and
 *   preventive records scoped to knitting machines.
 * - adds 'knitting_mechanic' to both manufacturing_role ENUMs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knitting_machine_setups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('fabric_id')->nullable()->constrained('fabrics')->nullOnDelete();
            $table->string('task_type', 32)->default('setup'); // setup|changeover|calibration|preventive|repair
            $table->json('settings')->nullable(); // stitch_length, yarn_tension, takeup_pressure, gsm_target, cams_config, pattern_ref
            $table->string('status', 32)->default('pending'); // pending|in_progress|done
            $table->text('remarks')->nullable();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->string('shift', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE `users` MODIFY COLUMN `manufacturing_role` " .
            "ENUM('knitting_yarn','knitting_mechanic','dyeing_color'," .
            "'dyeing_fabric_softener','dyeing_squeezer','dyeing_ironing'," .
            "'dyeing_forming','dyeing_packaging','maintenance_checker'," .
            "'checker_quality') NULL"
        );
        DB::statement(
            "ALTER TABLE `manufacturing_supervisor_roles` MODIFY COLUMN `manufacturing_role` " .
            "ENUM('knitting_yarn','knitting_mechanic','dyeing_color'," .
            "'dyeing_fabric_softener','dyeing_squeezer','dyeing_ironing'," .
            "'dyeing_forming','dyeing_packaging','maintenance_checker'," .
            "'checker_quality')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('knitting_machine_setups');

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE `users` MODIFY COLUMN `manufacturing_role` " .
            "ENUM('knitting_yarn','dyeing_color'," .
            "'dyeing_fabric_softener','dyeing_squeezer','dyeing_ironing'," .
            "'dyeing_forming','dyeing_packaging','maintenance_checker'," .
            "'checker_quality') NULL"
        );
        DB::statement(
            "ALTER TABLE `manufacturing_supervisor_roles` MODIFY COLUMN `manufacturing_role` " .
            "ENUM('knitting_yarn','dyeing_color'," .
            "'dyeing_fabric_softener','dyeing_squeezer','dyeing_ironing'," .
            "'dyeing_forming','dyeing_packaging','maintenance_checker'," .
            "'checker_quality')"
        );
    }
};
