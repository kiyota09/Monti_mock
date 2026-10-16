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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            // Basic Company Information
            $table->string('company_name');
            $table->string('business_type'); // e.g., Retailer, Manufacturer, Wholesaler
            $table->string('tin_number')->unique()->nullable(); // Tax Identification Number

            // Authentication & Contact
            $table->string('contact_person'); // Primary person to talk to
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone');

            // Address Details
            $table->text('company_address');
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();

            // Business/ERP Specific Fields
            // Use 'pending' initially so your HRM/FIN can verify the business first
            $table->enum('status', ['pending', 'active', 'suspended', 'rejected'])
                ->default('pending');

            // Financials (Useful for your Finance/Order Management modules)
            $table->decimal('credit_limit', 15, 2)->default(0.00);
            $table->integer('payment_terms_days')->default(30); // e.g., Net 30 terms

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); // Keep records of deleted clients for audit trails
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
