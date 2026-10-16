<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. The Main Lightweight Quotation Table
        Schema::create('eco_quotations', function (Blueprint $table) {
            $table->id();
            // Locks the quote to the exact client and chat history
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            
            $table->string('quotation_number')->unique();
            
            // Your exact form fields
            $table->date('delivery_date');
            $table->string('payment_terms');
            $table->text('notes')->nullable();
            
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->string('status')->default('sent'); // sent, accepted, rejected
            
            // Client response tracking
            $table->text('reject_reason')->nullable();
            $table->boolean('request_new_quote')->default(false);
            
            $table->timestamps();
        });

        // 2. The Items Table (Your specific fabric rows)
        Schema::create('eco_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eco_quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable(); // Auto-linked if names match
            
            // Your exact item form fields
            $table->string('fabric');
            $table->string('design')->nullable();
            $table->string('color');
            $table->decimal('kilos', 10, 2);
            $table->decimal('price', 12, 2);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eco_quotation_items');
        Schema::dropIfExists('eco_quotations');
    }
};