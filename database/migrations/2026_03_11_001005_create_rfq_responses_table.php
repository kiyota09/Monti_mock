<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('request_for_quotations')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('supplier_name');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 15, 2);
            $table->string('lead_time');                    // e.g. "10 days"
            $table->date('validity_date');                  // Quotation valid until
            $table->string('payment_terms')->default('Net 30');
            $table->text('notes')->nullable();
            $table->string('decline_reason')->nullable();   // If declined
            $table->date('submitted_at')->nullable();
            $table->enum('status', [
                'pending_review',
                'accepted',
                'declined',
            ])->default('pending_review');
            $table->timestamps();

            $table->index(['rfq_id', 'status']);
            $table->unique(['rfq_id', 'supplier_id']);      // One response per supplier per RFQ
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_responses');
    }
};
