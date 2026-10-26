<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tunable payroll constants (rates, divisors, shift times, calendar,
     * thresholds) + their change history. Values live in payroll_settings;
     * every update appends to payroll_setting_histories.
     */
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('group', 40)->default('general');
            $table->string('label', 120);
            $table->text('value')->nullable();
            $table->string('value_type', 20)->default('text'); // number|text|time|boolean
            $table->string('description', 255)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_setting_histories', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 80)->index();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_setting_histories');
        Schema::dropIfExists('payroll_settings');
    }
};
