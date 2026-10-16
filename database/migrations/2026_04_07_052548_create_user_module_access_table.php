<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_module_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('module'); // e.g., 'HRM', 'SCM', 'MAN', etc.
            $table->foreignId('granted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['user_id', 'module']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_module_access');
    }
};