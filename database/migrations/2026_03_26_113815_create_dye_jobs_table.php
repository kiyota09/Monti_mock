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
        Schema::create('dye_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_id')->constrained()->onDelete('cascade');
            $table->foreignId('machine_id')->nullable()->constrained();
            $table->string('dye_type');
            $table->string('chemical_no');
            $table->text('remarks')->nullable();
            $table->foreignId('operator_id')->constrained('users');
            $table->string('shift');
            $table->string('code')->unique(); // CHEM-2026-03-00001
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dye_jobs');
    }
};
