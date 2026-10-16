<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('scm_access_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('granted_by')->constrained('users')->onDelete('cascade');
            $table->boolean('can_access_scm')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('scm_access_permissions');
    }
};