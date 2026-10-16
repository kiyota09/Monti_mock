<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Industry-standard order lifecycle support for the ORD module.
     *
     * - purchase_orders / sales_orders get planning fields (expected ship
     *   date, priority, confirmation + milestone timestamps, hold/cancel
     *   reasons). All columns are nullable/additive: existing ECO / SCM /
     *   MAN writers keep working untouched.
     * - order_status_histories is the audit trail behind every guarded
     *   transition (who moved what, from -> to, when, why).
     * - order_returns is a lightweight RMA table for delivery shortages,
     *   rejects and client returns linked back to the sales order.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->date('expected_ship_date')->nullable()->after('delivery_date');
            $table->string('priority', 20)->default('normal')->after('expected_ship_date');
            $table->timestamp('confirmed_at')->nullable()->after('priority');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')
                ->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable()->after('confirmed_by');
            $table->string('on_hold_reason')->nullable()->after('cancel_reason');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->date('expected_ship_date')->nullable()->after('status');
            $table->string('priority', 20)->default('normal')->after('expected_ship_date');
            $table->timestamp('confirmed_at')->nullable()->after('priority');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('production_started_at')->nullable()->after('confirmed_by');
            $table->timestamp('production_done_at')->nullable()->after('production_started_at');
            $table->timestamp('delivered_at')->nullable()->after('production_done_at');
            $table->string('cancel_reason')->nullable()->after('delivered_at');
            $table->string('on_hold_reason')->nullable()->after('cancel_reason');
            $table->foreignId('created_by')->nullable()->after('on_hold_reason')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('orderable');
            $table->string('order_type', 10);
            $table->unsignedBigInteger('order_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['order_type', 'order_id']);
        });

        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('sales_order_id')->constrained()->onDelete('cascade');
            $table->string('type', 30)->default('shortage');
            $table->decimal('quantity', 10, 2);
            $table->text('reason');
            $table->string('status', 30)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_returns');
        Schema::dropIfExists('order_status_histories');

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'expected_ship_date', 'priority', 'confirmed_at', 'confirmed_by',
                'production_started_at', 'production_done_at', 'delivered_at',
                'cancel_reason', 'on_hold_reason',
            ]);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn([
                'expected_ship_date', 'priority', 'confirmed_at',
                'cancel_reason', 'on_hold_reason',
            ]);
        });
    }
};
