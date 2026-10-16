<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_for_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('rfq_number')->unique();                               // RFQ-2026-001
            $table->string('mr_ref')->nullable();                                 // MR reference
            $table->foreignId('mr_id')->nullable()->constrained('material_requests')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->string('material_name');
            $table->string('category');
            $table->string('unit');
            $table->decimal('required_qty', 12, 2);
            $table->date('deadline');                                             // Response deadline
            $table->date('sent_at')->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('payment_terms')->default('Net 30');
            $table->text('notes')->nullable();
            $table->json('supplier_ids')->nullable();                             // Array of supplier IDs sent to
            $table->enum('status', [
                'draft',
                'sent',
                'partial_response',   // at least one response received
                'responded',          // all expected responses received
                'closed',
                'cancelled',
            ])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('mr_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_for_quotations');
    }
};
