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
        Schema::create('lead_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->dateTime('scheduled_at');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_interviews');
    }
};
