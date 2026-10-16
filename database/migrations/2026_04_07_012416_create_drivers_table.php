<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('license_number')->unique();
            $table->string('license_image')->nullable();
            $table->string('medical_certificate')->nullable();
            $table->decimal('rating', 2, 1)->default(5.0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('drivers');
    }
};