<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('inquiry_id')->nullable()->after('client_id')
                ->constrained('inquiries')->nullOnDelete();
            $table->foreignId('eco_quotation_id')->nullable()->after('inquiry_id')
                ->constrained('eco_quotations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('eco_quotation_id');
            $table->dropConstrainedForeignId('inquiry_id');
        });
    }
};
