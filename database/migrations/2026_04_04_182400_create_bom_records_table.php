<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('bom_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->string('yarn_type');
            $table->string('dye_color');
            $table->string('weave_design');
            $table->json('materials')->comment('{material_id: quantity}');
            $table->timestamps();
            $table->unique(['client_id', 'product_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bom_records');
    }
};