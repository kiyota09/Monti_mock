<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conductor_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conductor_id')->constrained('conductors')->cascadeOnDelete();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_stop_id')->nullable()->constrained('delivery_stops')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->enum('method', ['cash', 'cheque', 'bank_transfer', 'e_wallet', 'credit'])->default('cash');
            $table->string('reference')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('receipt_path')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['pending', 'remitted', 'verified', 'void'])->default('pending');
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('remitted_at')->nullable();
            $table->timestamps();

            $table->index(['conductor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conductor_collections');
    }
};
