<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boiler_machine_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boiler_machine_id')->constrained('machines')->cascadeOnDelete();
            $table->foreignId('steam_machine_id')->constrained('machines')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['boiler_machine_id', 'steam_machine_id'], 'boiler_machine_links_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boiler_machine_links');
    }
};
