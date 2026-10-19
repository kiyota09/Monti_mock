<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('truck_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['delay', 'accident', 'breakdown', 'other'])->default('delay');
            $table->string('category')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description');
            $table->decimal('location_lat', 10, 8)->nullable();
            $table->decimal('location_lng', 11, 8)->nullable();
            $table->string('location_text')->nullable();
            $table->text('third_party_info')->nullable();
            $table->string('photo_path')->nullable();
            $table->enum('status', ['open', 'reviewed', 'resolved'])->default('open');
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->index(['driver_id', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_incidents');
    }
};
