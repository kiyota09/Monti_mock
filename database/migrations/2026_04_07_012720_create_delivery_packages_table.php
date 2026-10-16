<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('delivery_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_package_id')->constrained('warehouse_packages')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('delivery_packages');
    }
};