<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Finance module ledger tables. Receivables invoices are synced from
     * sales orders (one invoice per job order); bills, expenses and
     * budgets are recorded by FIN staff. Payments live in dedicated
     * ledger tables so paid totals never drift from history.
     */
    public function up(): void
    {
        Schema::create('fin_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->unsignedBigInteger('sales_order_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('client_name')->default('N/A');
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid')->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('fin_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fin_invoice_id')->constrained('fin_invoices')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('paid_at');
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });

        Schema::create('fin_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_no')->unique();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->string('supplier_name')->default('N/A');
            $table->string('category')->default('Materials');
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid')->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('fin_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fin_bill_id')->constrained('fin_bills')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('paid_at');
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });

        Schema::create('fin_expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date')->index();
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('department')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });

        Schema::create('fin_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('department');
            $table->string('period', 7)->comment('YYYY-MM');
            $table->decimal('allocated', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['department', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_budgets');
        Schema::dropIfExists('fin_expenses');
        Schema::dropIfExists('fin_bill_payments');
        Schema::dropIfExists('fin_bills');
        Schema::dropIfExists('fin_invoice_payments');
        Schema::dropIfExists('fin_invoices');
    }
};
