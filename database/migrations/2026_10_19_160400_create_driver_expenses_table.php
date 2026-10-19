<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['fuel', 'toll', 'scale', 'repair', 'other'])->default('fuel');
            $table->decimal('amount', 10, 2);
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->date('spent_at');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index(['driver_id', 'spent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_expenses');
    }
};
