<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only salary ledger. Rows are never edited — corrections
        // are new rows (effective-dated), preserving what applied when.
        Schema::create('salary_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('previous_salary', 12, 2)->nullable();
            $table->decimal('new_salary', 12, 2)->nullable();
            $table->date('effective_date');
            $table->string('reason', 255)->nullable();
            // initial | adjustment | directory | correction
            $table->string('source', 30)->default('adjustment');
            $table->foreignId('salary_adjustment_id')->nullable()->constrained('salary_adjustments')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_history');
    }
};
