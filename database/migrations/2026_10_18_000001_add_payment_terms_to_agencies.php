<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('billing_cycle', 40)->nullable()->after('contract_end');
            $table->string('payment_terms', 60)->nullable()->after('billing_cycle');
            $table->string('payment_custom_terms', 255)->nullable()->after('payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle', 'payment_terms', 'payment_custom_terms']);
        });
    }
};
