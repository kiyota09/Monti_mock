<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knitting_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_id')->constrained('fabrics')->cascadeOnDelete();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result', 20);
            $table->string('destination', 30)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['inspector_id', 'created_at']);
            $table->index(['fabric_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knitting_inspections');
    }
};
