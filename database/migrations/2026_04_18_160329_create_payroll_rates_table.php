<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payroll_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('daily_rate', 12, 2)->default(537.00);
            $table->decimal('daily_rate_usd', 12, 2)->nullable();
            $table->decimal('overtime_rate', 5, 2)->default(125.00);
            $table->decimal('night_diff_rate', 5, 2)->default(10.00);
            $table->decimal('holiday_rate', 5, 2)->default(200.00);
            $table->decimal('special_holiday_rate', 5, 2)->default(130.00);
            $table->decimal('rest_day_rate', 5, 2)->default(130.00);
            $table->decimal('late_deduction_per_minute', 8, 2)->default(1.50);
            $table->decimal('sss_rate', 5, 2)->default(4.5);
            $table->decimal('philhealth_rate', 5, 2)->default(3.0);
            $table->decimal('pagibig_rate', 5, 2)->default(2.0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payroll_rates');
    }
};