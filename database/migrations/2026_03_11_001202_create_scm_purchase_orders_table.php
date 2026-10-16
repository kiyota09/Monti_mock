<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SCM Purchase Orders (internal procurement POs — separate from client POs)
        Schema::create('scm_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();                          // SCMPO-2026-0001
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('supplier_name');
            $table->string('rfq_ref')->nullable();                          // RFQ reference
            $table->foreignId('rfq_id')->nullable()->constrained('request_for_quotations')->nullOnDelete();
            $table->date('issued_date');
            $table->string('expected_delivery')->nullable();                // can be a date or "10 days"
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->unsignedTinyInteger('tax_rate')->default(10);           // percent
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('received')->default(false);
            $table->enum('status', [
                'draft',
                'sent',
                'confirmed',         // supplier acknowledged
                'partially_received',
                'received',
                'cancelled',
            ])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'supplier_id']);
        });

        // SCM PO Line Items
        Schema::create('scm_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scm_purchase_order_id')
                ->constrained('scm_purchase_orders')
                ->cascadeOnDelete();
            $table->foreignId('material_id')
                ->nullable()
                ->constrained('materials')
                ->nullOnDelete();
            $table->string('material_name');
            $table->decimal('qty', 12, 2);
            $table->string('unit');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scm_purchase_order_items');
        Schema::dropIfExists('scm_purchase_orders');
    }
};
