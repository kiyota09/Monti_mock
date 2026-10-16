<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('workforce_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('module')->nullable(); // HRM, ECO, CRM, etc. null means all modules
            $table->string('department')->nullable(); // e.g., 'dyeing', 'knitting', null for whole module
            $table->enum('access_level', ['view', 'schedule', 'manage'])->default('view');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('workforce_permissions');
    }
};
