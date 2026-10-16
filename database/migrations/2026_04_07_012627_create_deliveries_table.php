<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->foreignId('truck_id')->nullable()->constrained();
            $table->foreignId('driver_id')->nullable()->constrained('drivers');
            $table->foreignId('conductor1_id')->nullable()->constrained('conductors');
            $table->foreignId('conductor2_id')->nullable()->constrained('conductors');
            $table->foreignId('route_id')->nullable()->constrained();
            $table->enum('status', ['pending', 'dispatched', 'in_transit', 'delivered', 'cancelled'])->default('pending');
            $table->timestamp('scheduled_departure')->nullable();
            $table->timestamp('actual_departure')->nullable();
            $table->timestamp('arrival_time')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('deliveries');
    }
};