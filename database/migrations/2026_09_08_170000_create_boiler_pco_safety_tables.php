<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Boiler sub-department (under Maintenance) + Pollution Control and
 * Safety roles (Maintenance department):
 * - boiler_logs: shift operating log per boiler unit (steam, water,
 *   fuel, blowdown, dosing) tied to machines of type 'boiler'.
 * - pco_records: effluent / emission / waste compliance log
 *   (DENR-style parameter vs limit tracking).
 * - safety_incidents: injury / near-miss / fire / spill reports
 *   with corrective-action workflow (DOLE OSH style).
 * Adds 'boiler' to supervisor_department and the three new roles
 * to both manufacturing_role ENUMs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boiler_logs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->decimal('steam_pressure', 8, 2)->nullable(); // psi or bar per plant standard
            $table->string('water_level', 32)->nullable(); // low|normal|high
            $table->decimal('fuel_used', 10, 2)->nullable();
            $table->string('fuel_unit', 16)->nullable(); // L|kg
            $table->boolean('blowdown_done')->default(false);
            $table->string('chemical_dosing')->nullable();
            $table->decimal('operating_hours', 8, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->string('shift', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pco_records', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            // effluent|emission|solid_waste|chemical_handling|noise
            $table->string('record_type', 32)->default('effluent');
            $table->string('location')->nullable(); // e.g. wastewater treatment outlet
            $table->string('parameter')->nullable(); // e.g. pH, BOD, TSS
            $table->string('value')->nullable();
            $table->string('unit')->nullable();
            $table->string('standard_limit')->nullable(); // DENR standard for the parameter
            $table->boolean('compliant')->default(true);
            $table->date('recorded_date');
            $table->text('remarks')->nullable();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->string('shift', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            // injury|near_miss|fire|chemical_spill|equipment|other
            $table->string('incident_type', 32)->default('near_miss');
            $table->string('location')->nullable();
            $table->string('department', 32)->nullable(); // knitting|dyeing|finishing|maintenance|warehouse|other
            $table->date('incident_date');
            // minor|moderate|major|critical
            $table->string('severity', 16)->default('minor');
            $table->text('description');
            $table->text('corrective_action')->nullable();
            // open|investigating|closed
            $table->string('status', 32)->default('open');
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->string('shift', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $roles = "'knitting_yarn','knitting_mechanic','dyeing_color'," .
            "'dyeing_fabric_softener','dyeing_squeezer','dyeing_ironing'," .
            "'dyeing_lab_chemist','dyeing_forming','dyeing_packaging'," .
            "'maintenance_checker','pollution_control_operator'," .
            "'safety_officer','boiler_operator','checker_quality'";

        DB::statement("ALTER TABLE `users` MODIFY COLUMN `manufacturing_role` ENUM({$roles}) NULL");
        DB::statement("ALTER TABLE `manufacturing_supervisor_roles` MODIFY COLUMN `manufacturing_role` ENUM({$roles})");
        DB::statement(
            "ALTER TABLE `users` MODIFY COLUMN `supervisor_department` " .
            "ENUM('knitting','dyeing','finishing','maintenance','boiler') NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_incidents');
        Schema::dropIfExists('pco_records');
        Schema::dropIfExists('boiler_logs');

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $roles = "'knitting_yarn','knitting_mechanic','dyeing_color'," .
            "'dyeing_fabric_softener','dyeing_squeezer','dyeing_ironing'," .
            "'dyeing_lab_chemist','dyeing_forming','dyeing_packaging'," .
            "'maintenance_checker','checker_quality'";

        DB::statement("ALTER TABLE `users` MODIFY COLUMN `manufacturing_role` ENUM({$roles}) NULL");
        DB::statement("ALTER TABLE `manufacturing_supervisor_roles` MODIFY COLUMN `manufacturing_role` ENUM({$roles})");
        DB::statement(
            "ALTER TABLE `users` MODIFY COLUMN `supervisor_department` " .
            "ENUM('knitting','dyeing','finishing','maintenance') NULL"
        );
    }
};
