<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dyeing Lab Chemist role (dyeing department):
 * lab-dip shade development, recipe calculation, fastness testing
 * with CoA data, lab stock solutions, and lab-to-bulk transfer.
 * Adds 'dyeing_lab_chemist' to both manufacturing_role ENUMs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_dip_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->string('customer_ref')->nullable();
            $table->string('pantone_code')->nullable();
            $table->string('rgb_lab_values')->nullable();
            $table->string('swatch_id')->nullable();
            $table->string('fabric_details')->nullable();
            $table->string('urgency', 16)->default('normal'); // low|normal|high|urgent
            // pending|in_progress|awaiting_spectro|approved|rejected
            $table->string('status', 32)->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->string('shift', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_trials', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('dip_request_id')->constrained('lab_dip_requests')->cascadeOnDelete();
            $table->unsignedInteger('trial_no')->default(1);
            // { dyestuffs: [{name, pct}], auxiliaries: [{name, gpl}], liquor_ratio, curve: [{step, temp, rate, hold, dosing}] }
            $table->json('formula')->nullable();
            $table->text('adjustments')->nullable();
            $table->decimal('delta_e', 8, 2)->nullable();
            $table->json('spectro_readings')->nullable(); // integration seam for live instrument feed
            $table->string('status', 32)->default('pending'); // pending|passed|failed
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->string('shift', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('dip_request_id')->constrained('lab_dip_requests')->cascadeOnDelete();
            $table->string('test_type', 64); // wash|rub_dry|rub_wet|light|perspiration|ph|absorbency|other
            $table->string('method')->nullable();
            $table->string('rating', 16)->nullable(); // gray scale 1-5 or measured value
            $table->text('result')->nullable();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->string('shift', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_stock_solutions', function (Blueprint $table) {
            $table->id();
            $table->string('material_name');
            $table->string('concentration')->nullable(); // e.g. 1%, 0.1%
            $table->string('lot_no')->nullable();
            $table->date('prepared_at')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('sds_url')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $roles = "'knitting_yarn','knitting_mechanic','dyeing_color'," .
            "'dyeing_fabric_softener','dyeing_squeezer','dyeing_ironing'," .
            "'dyeing_lab_chemist','dyeing_forming','dyeing_packaging'," .
            "'maintenance_checker','checker_quality'";

        DB::statement("ALTER TABLE `users` MODIFY COLUMN `manufacturing_role` ENUM({$roles}) NULL");
        DB::statement("ALTER TABLE `manufacturing_supervisor_roles` MODIFY COLUMN `manufacturing_role` ENUM({$roles})");
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_stock_solutions');
        Schema::dropIfExists('lab_tests');
        Schema::dropIfExists('lab_trials');
        Schema::dropIfExists('lab_dip_requests');

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $roles = "'knitting_yarn','knitting_mechanic','dyeing_color'," .
            "'dyeing_fabric_softener','dyeing_squeezer','dyeing_ironing'," .
            "'dyeing_forming','dyeing_packaging'," .
            "'maintenance_checker','checker_quality'";

        DB::statement("ALTER TABLE `users` MODIFY COLUMN `manufacturing_role` ENUM({$roles}) NULL");
        DB::statement("ALTER TABLE `manufacturing_supervisor_roles` MODIFY COLUMN `manufacturing_role` ENUM({$roles})");
    }
};
