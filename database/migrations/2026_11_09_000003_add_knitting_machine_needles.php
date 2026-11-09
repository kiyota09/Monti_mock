<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->unsignedInteger('needle_count')->default(0)->after('load_capacity_kg');
        });

        Schema::create('knitting_machine_needle_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('replaced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('needle_count_after');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['machine_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knitting_machine_needle_replacements');
        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn('needle_count');
        });
    }
};
