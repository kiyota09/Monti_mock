<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('guard', 24);
            $table->unsignedBigInteger('user_id');
            $table->string('theme', 16);
            $table->timestamps();
            $table->unique(['guard', 'user_id']);
            $table->index('theme');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_preferences');
    }
};
