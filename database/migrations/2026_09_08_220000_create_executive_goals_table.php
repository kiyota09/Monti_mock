<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Executive goals & targets: quarterly/monthly department targets
 * compared against actuals computed from module data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executive_goals', function (Blueprint $table) {
            $table->id();
            $table->string('department', 32); // knitting|dyeing|finishing|maintenance|boiler|sales|finance|hr|company
            $table->string('metric_key', 64); // output|reject_rate|revenue|payroll_cost|packages|leads_won|incidents
            $table->string('metric_label');
            $table->string('unit', 32)->nullable(); // pcs|%|₱|count
            $table->decimal('target', 16, 2);
            // Lower-is-better metrics (reject_rate, payroll_cost, incidents).
            $table->boolean('lower_is_better')->default(false);
            $table->date('period'); // first day of the target month
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_goals');
    }
};
