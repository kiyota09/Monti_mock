<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_vehicle_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('truck_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['pre_trip', 'post_trip'])->default('pre_trip');
            $table->json('checklist')->nullable();
            $table->text('defects')->nullable();
            $table->enum('severity', ['none', 'minor', 'critical'])->default('none');
            $table->string('photo_path')->nullable();
            $table->boolean('passed')->default(true);
            $table->timestamp('inspected_at');
            $table->timestamps();

            $table->index(['driver_id', 'inspected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_vehicle_inspections');
    }
};
