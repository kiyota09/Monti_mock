<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            // Visit order along the optimized route (1 = first drop-off).
            $table->unsignedInteger('sequence')->default(0);

            $table->string('label')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->enum('status', ['pending', 'arrived', 'delivered'])->default('pending');
            $table->timestamp('arrived_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['delivery_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_stops');
    }
};
