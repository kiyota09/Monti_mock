<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_accounts', function (Blueprint $table) {
            $table->id();
            // Link directly to your existing business clients table
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->boolean('is_good_payer')->default(true); // Flag for the ECO Manager's manual review
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_accounts');
    }
};
