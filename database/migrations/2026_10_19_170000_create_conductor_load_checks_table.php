<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conductor_load_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conductor_id')->constrained('conductors')->cascadeOnDelete();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['pre_trip', 'post_trip'])->default('pre_trip');
            $table->json('checklist')->nullable();
            $table->text('discrepancies')->nullable();
            $table->enum('severity', ['none', 'minor', 'critical'])->default('none');
            $table->string('photo_path')->nullable();
            $table->boolean('passed')->default(true);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['delivery_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conductor_load_checks');
    }
};
