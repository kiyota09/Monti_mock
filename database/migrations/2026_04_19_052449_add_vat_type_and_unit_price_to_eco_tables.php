<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add VAT type to quotations header
        Schema::table('eco_quotations', function (Blueprint $table) {
            $table->string('vat_type', 20)->default('exclusive')->after('payment_terms');
        });

        // Add per-kg unit price alongside the existing item total
        Schema::table('eco_quotation_items', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->default(0.00)->after('kilos');
        });
    }

    public function down(): void
    {
        Schema::table('eco_quotations', function (Blueprint $table) {
            $table->dropColumn('vat_type');
        });

        Schema::table('eco_quotation_items', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};