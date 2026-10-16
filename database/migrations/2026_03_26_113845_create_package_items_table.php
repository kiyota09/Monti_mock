<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_job_id')->constrained()->onDelete('cascade');
            $table->integer('quantity'); // how many from that form job
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('package_items');
    }
};
