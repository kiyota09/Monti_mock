<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('page_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('module'); // e.g., 'HRM', 'ECO', etc.
            $table->string('page');    // e.g., 'application', 'interview', etc.
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('page_permissions');
    }
};
