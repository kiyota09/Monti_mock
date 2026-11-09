<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payrolls', 'advance_deduction')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->decimal('advance_deduction', 15, 2)->default(0)->after('pf_loan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payrolls', 'advance_deduction')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropColumn('advance_deduction');
            });
        }
    }
};
