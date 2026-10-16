<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('warehouse_shelves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('warehouse_sections')->cascadeOnDelete();
            $table->string('shelf_number');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_shelves');
    }
};