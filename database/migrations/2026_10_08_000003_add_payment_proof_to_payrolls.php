<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('payrolls', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('payrolls', 'payment_reference')) {
                $table->string('payment_reference', 80)->nullable()->after('paid_by');
            }
            if (!Schema::hasColumn('payrolls', 'payment_method')) {
                $table->string('payment_method', 40)->nullable()->after('payment_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            foreach (['paid_at', 'paid_by', 'payment_reference', 'payment_method'] as $c) {
                if (Schema::hasColumn('payrolls', $c)) $table->dropColumn($c);
            }
        });
    }
};
