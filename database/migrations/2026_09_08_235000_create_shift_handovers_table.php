<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shift handover log: outgoing shifts record unfinished work, limping
 * machines, and hot jobs; the VP reads plant-wide continuity each morning.
 * Written by manufacturing supervisors from their overview page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_handovers', function (Blueprint $table) {
            $table->id();
            $table->string('department', 32); // knitting|dyeing|finishing|maintenance|boiler
            $table->date('shift_date');
            $table->string('shift_type', 64)->nullable(); // Morning|Afternoon|Night
            $table->text('unfinished_work')->nullable();
            $table->text('machine_notes')->nullable();
            $table->text('hot_jobs')->nullable();
            $table->text('safety_notes')->nullable();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_handovers');
    }
};
