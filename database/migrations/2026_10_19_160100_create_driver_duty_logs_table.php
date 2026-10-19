<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_duty_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['off_duty', 'on_duty', 'driving', 'on_break'])->default('on_duty');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('notes')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_duty_logs');
    }
};
