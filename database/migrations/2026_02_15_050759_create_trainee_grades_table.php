<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trainee_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // The trainee
            $table->integer('skills_performance')->default(0);
            $table->integer('behaviour')->default(0);
            $table->integer('technicals')->default(0);
            $table->integer('safety_awareness')->default(0);
            $table->integer('productivity')->default(0);
            $table->integer('total_percentage')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainee_grades');
    }
};
