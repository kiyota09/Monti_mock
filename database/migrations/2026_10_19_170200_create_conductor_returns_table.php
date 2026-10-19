<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conductor_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conductor_id')->constrained('conductors')->cascadeOnDelete();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_stop_id')->nullable()->constrained('delivery_stops')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('package_number')->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->enum('reason', ['damaged', 'wrong_item', 'shortage', 'overage', 'refused', 'expired', 'other'])->default('damaged');
            $table->enum('action', ['return_to_warehouse', 'replace', 'credit', 'dispose'])->default('return_to_warehouse');
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->index(['conductor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conductor_returns');
    }
};
