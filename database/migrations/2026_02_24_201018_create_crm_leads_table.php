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
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_person');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('interest_fabric'); // Knitting data
            $table->decimal('estimated_value', 15, 2);
            $table->enum('status', ['Inquiry', 'Negotiation', 'Approval Sent', 'Closed-Won', 'Lost'])->default('Inquiry');
            $table->foreignId('assigned_staff_id')->constrained('users');
            $table->text('lost_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
