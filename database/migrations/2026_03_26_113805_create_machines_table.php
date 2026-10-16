<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('machine_no')->unique();
            $table->string('type'); // knitting, dyeing, softening, squeezer, forming
            $table->enum('status', ['available', 'under_maintenance', 'retired'])->default('available');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('machines');
    }
};
