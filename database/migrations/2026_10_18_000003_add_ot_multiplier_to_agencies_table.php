<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-agency (contract) overtime billing multiplier. Kept separate from
     * Payroll's ot_multiplier because the agency's customer contract may
     * differ from the employee's payroll rule.
     */
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            if (! Schema::hasColumn('agencies', 'ot_multiplier')) {
                $table->decimal('ot_multiplier', 4, 2)->default(1.25)->after('service_fee_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('ot_multiplier');
        });
    }
};
