<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payroll_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Default Staff Rates"
            $table->enum('employee_type', ['staff', 'manager'])->default('staff');
            $table->decimal('base_salary', 15, 2);
            $table->decimal('ot_rate', 10, 2)->comment('Multiplier for overtime (e.g., 1.25)');
            $table->decimal('night_rate', 10, 2)->comment('Multiplier for night diff (e.g., 1.10)');
            $table->decimal('late_rate_per_minute', 10, 2);
            $table->decimal('sunday_rate', 10, 2)->comment('Multiplier for Sunday/rest day');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payroll_sets');
    }
};