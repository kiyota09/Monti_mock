<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('warehouse_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('grid_row')->nullable();
            $table->integer('grid_col')->nullable();
            $table->integer('capacity')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_sections');
    }
};