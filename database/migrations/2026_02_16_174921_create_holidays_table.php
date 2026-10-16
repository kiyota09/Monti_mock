<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('holiday_name');
            $table->enum('holiday_type', ['Normal', 'Special'])->default('Normal');
            $table->decimal('premium_rate', 5, 2)->default(1.00); // e.g., 2.00 for 200%
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
