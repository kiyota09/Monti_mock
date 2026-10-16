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
        Schema::create('vendor_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_registration_id')->constrained('vendor_registrations')->onDelete('cascade');
            $table->string('requirement_name');
            $table->text('description')->nullable();
            $table->string('value')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('vendor_registration_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_requirements');
    }
};
