<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->string('truck_number')->unique();
            $table->string('plate_number')->unique();
            $table->string('model');
            $table->integer('year');
            $table->decimal('mileage', 10, 2)->default(0);
            $table->enum('status', ['available', 'in_use', 'under_maintenance', 'retired'])->default('available');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('trucks');
    }
};