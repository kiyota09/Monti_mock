<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_adjustment_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_adjustment_id')->constrained('salary_adjustments')->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 20);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('salary_adjustment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_adjustment_approvals');
    }
};
