<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purchase Invoices — submitted by suppliers against a PO
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();                         // INV-HTM-8821
            $table->foreignId('po_id')
                ->nullable()
                ->constrained('scm_purchase_orders')
                ->nullOnDelete();
            $table->string('po_number')->nullable();
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete();
            $table->string('supplier_name');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_terms')->default('Net 30');
            $table->date('received_at')->nullable();
            $table->enum('status', [
                'awaiting',     // PO sent but invoice not yet received
                'unpaid',       // Invoice received, payment pending
                'partial',      // Partially paid
                'paid',         // Fully paid
                'disputed',     // Under dispute
                'cancelled',
            ])->default('unpaid');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_date']);
            $table->index('supplier_id');
        });

        // Procurement Payments — payment records for invoices
        Schema::create('procurement_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();                         // PAY-2026-051
            $table->foreignId('invoice_id')
                ->constrained('purchase_invoices')
                ->cascadeOnDelete();
            $table->string('invoice_number');
            $table->string('supplier_name');
            $table->date('paid_date');
            $table->decimal('amount', 15, 2);
            $table->string('method');                                           // Bank Transfer, Check, etc.
            $table->string('bank_reference')->nullable();                       // TXN reference number
            $table->text('remarks')->nullable();
            $table->enum('status', [
                'pending',
                'cleared',
                'failed',
                'reversed',
            ])->default('pending');
            $table->timestamps();

            $table->index(['status', 'paid_date']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_payments');
        Schema::dropIfExists('purchase_invoices');
    }
};
