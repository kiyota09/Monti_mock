<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('government_contribution_rates', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'sss', 'philhealth', 'pagibig', 'tax'
            $table->string('bracket_name')->nullable();
            $table->decimal('min_compensation', 15, 2)->nullable();
            $table->decimal('max_compensation', 15, 2)->nullable();
            $table->decimal('employee_share', 10, 4)->nullable();
            $table->decimal('employer_share', 10, 4)->nullable();
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->decimal('percentage', 10, 4)->nullable();
            $table->json('meta')->nullable(); // for future flexibility
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('government_contribution_rates');
    }
};