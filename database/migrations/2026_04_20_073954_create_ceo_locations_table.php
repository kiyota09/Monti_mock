<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ceo_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('latitude', 11, 8);
            $table->decimal('longitude', 11, 8);
            $table->integer('range_radius'); // In meters
            $table->string('label')->nullable(); // Optional: e.g., "Warehouse A Perimeter"
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('ceo_locations');
    }
};