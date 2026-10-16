<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('origin');
            $table->string('destination');
            $table->decimal('distance_km', 8, 2);
            $table->integer('estimated_minutes');
            $table->json('waypoints')->nullable(); // for GPS tracking
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('routes');
    }
};