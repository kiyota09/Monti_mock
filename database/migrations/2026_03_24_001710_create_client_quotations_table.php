<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('client_quotations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('quotation_number')->unique();
            $table->string('rfq_reference')->nullable();
            $table->date('issue_date');
            $table->date('valid_until');
            $table->enum('status', ['draft', 'sent', 'under_review', 'accepted', 'rejected', 'expired', 'converted'])->default('draft');
            $table->unsignedBigInteger('prepared_by')->nullable(); // user_id (ECO staff/manager)
            $table->text('manufacturer_info')->nullable();
            $table->text('billing_address');
            $table->text('shipping_address');
            $table->string('lead_time')->nullable();
            $table->string('incoterms')->nullable();
            $table->string('shipping_method')->nullable();
            $table->string('payment_terms');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2);
            $table->string('currency', 3)->default('PHP');
            $table->text('terms_conditions')->nullable();
            $table->text('custom_notes')->nullable();
            $table->timestamp('client_accepted_at')->nullable();
            $table->timestamp('client_rejected_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('prepared_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('client_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quotation_id');
            $table->unsignedBigInteger('product_id'); // from inv products
            $table->string('product_sku');
            $table->string('product_name');
            $table->text('product_description')->nullable();
            $table->string('technical_specs')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->string('unit_of_measure', 10);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();

            $table->foreign('quotation_id')->references('id')->on('client_quotations')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        // Add is_good_payer boolean to clients table if not exists
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'is_good_payer')) {
                $table->boolean('is_good_payer')->default(true);
            }
            if (! Schema::hasColumn('clients', 'credit_risk_notes')) {
                $table->text('credit_risk_notes')->nullable();
            }
            if (! Schema::hasColumn('clients', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable();
            }
            if (! Schema::hasColumn('clients', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::dropIfExists('client_quotation_items');
        Schema::dropIfExists('client_quotations');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['is_good_payer', 'credit_risk_notes', 'latitude', 'longitude']);
        });
    }
};
