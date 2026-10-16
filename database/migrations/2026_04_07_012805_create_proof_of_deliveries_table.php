<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('proof_of_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->string('image_path');
            $table->text('notes')->nullable();
            $table->timestamp('delivered_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('proof_of_deliveries');
    }
};