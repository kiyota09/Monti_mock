<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('conductor_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->foreignId('conductor_id')->constrained('conductors');
            $table->text('report_text');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('conductor_reports');
    }
};